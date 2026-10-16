import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.InetSocketAddress
import java.net.Socket
import java.nio.ByteBuffer
import java.nio.ByteOrder
import java.util.zip.CRC32
import javax.net.ssl.SSLSocket
import javax.net.ssl.SSLSocketFactory

object MobileGpsProtocol {
    fun encode(code: Int, payload: JSONObject): String {
        require(code in 0..255)
        val json = payload.toString().toByteArray(Charsets.UTF_8)
        require(json.size <= 16384)
        val body = ByteBuffer.allocate(8 + json.size).order(ByteOrder.BIG_ENDIAN)
            .put(0x54.toByte()).put(0x47.toByte()).put(1.toByte()).put(code.toByte())
            .putInt(json.size).put(json).array()
        val crc = CRC32().apply { update(body) }.value
        val frame = ByteBuffer.allocate(body.size + 4).order(ByteOrder.BIG_ENDIAN)
            .put(body).putInt(crc.toInt()).array()
        return frame.joinToString("") { "%02X".format(it.toInt() and 0xff) } + "\n"
    }

    fun decode(line: String): Pair<Int, JSONObject> {
        val hex = line.trimEnd('\r', '\n')
        require(hex.length in 24..32792 && hex.length % 2 == 0)
        val bytes = hex.chunked(2).map { it.toInt(16).toByte() }.toByteArray()
        val frame = ByteBuffer.wrap(bytes).order(ByteOrder.BIG_ENDIAN)
        require(frame.get() == 0x54.toByte() && frame.get() == 0x47.toByte())
        require(frame.get() == 1.toByte())
        val code = frame.get().toInt() and 0xff
        val size = frame.int
        require(size in 0..16384 && bytes.size == size + 12)
        val json = ByteArray(size).also { frame.get(it) }
        val expected = frame.int.toLong() and 0xffffffffL
        val actual = CRC32().apply { update(bytes, 0, bytes.size - 4) }.value
        require(expected == actual)
        return code to JSONObject(String(json, Charsets.UTF_8))
    }

    // Call from an IO coroutine/executor, never Android's main UI thread.
    fun send(host: String, port: Int, code: Int, payload: JSONObject): Pair<Int, JSONObject> {
        val tcp = Socket()
        try {
            tcp.connect(InetSocketAddress(host, port), 10000)
            tcp.soTimeout = 10000
            val socket = (SSLSocketFactory.getDefault() as SSLSocketFactory)
                .createSocket(tcp, host, port, true) as SSLSocket
            socket.use {
                it.sslParameters = it.sslParameters.apply { endpointIdentificationAlgorithm = "HTTPS" }
                it.startHandshake()
                it.outputStream.write(encode(code, payload).toByteArray(Charsets.US_ASCII))
                it.outputStream.flush()
                val reader = BufferedReader(InputStreamReader(it.inputStream, Charsets.US_ASCII))
                val reply = StringBuilder()
                while (true) {
                    val character = reader.read()
                    check(character >= 0) { "Server disconnected" }
                    if (character == 10) break
                    check(reply.length < 32792) { "Oversized reply" }
                    reply.append(character.toChar())
                }
                return decode(reply.toString())
            }
        } finally {
            tcp.close()
        }
    }
}
