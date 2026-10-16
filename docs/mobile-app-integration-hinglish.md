# Mobile App Banane Ki Guide: TG1 Hex Protocol

**Updated no-login flow:** [mobile-no-login.md](mobile-no-login.md) follow karein.
Ab user ko login nahi karna: user_id + valid licence + registered IMEI + one-time device_key bhejna hai. Neeche token/login examples legacy flow hain; naye app ke liye login required nahi hai. Hex framing same rahegi.

## 1. Abhi kis IP/port par bhejna hai?

3 October 2026 ko is PC ka Wi-Fi IPv4 address **192.168.1.60** mila.
Phone isi Wi-Fi/LAN par ho to destination host **192.168.1.60**, port **9000**
aur transport **TLS over TCP** configure karein.

Yeh address network par DHCP se badal sakta hai. Har setup par `ipconfig` se check
karein. Yeh public internet IP nahi hai. Mobile data ya doosre Wi-Fi se connect
karne ke liye deployed server ka reachable public IP/domain chahiye.

| Situation | App mein host | Port | Server setup |
| --- | --- | --- | --- |
| Isi PC ka local test client | 127.0.0.1 | 9000 | Default local TCP listener |
| Isi Wi-Fi par physical phone | 192.168.1.60 (current PC IP) | 9000 | LAN listener + trusted TLS certificate |
| Internet/mobile data | Apna deployed server domain/IP | 9000 | TLS, firewall, routing/public deployment |

Phone par 127.0.0.1 phone ko refer karta hai. 0.0.0.0 server ka bind address hai,
phone ka destination nahi. Listener port HTTP API port se alag hai. GPS hex
packet port 8001 ya HTTP URL par POST nahi hota; socket par port 9000 ko bhejna hai.

## 2. Terminal mein listener kaise chalana hai?

Tracking project ke PowerShell terminal mein:

```powershell
Set-Location D:\tracking\tracking2026
php artisan gps:listen --host=127.0.0.1 --port=9000
```

Yeh sirf isi PC ke local clients accept karega. LAN phone ke liye valid/trusted
TLS certificate aur private key ke saath:

```powershell
php artisan gps:listen --host=0.0.0.0 --port=9000 --cert=C:/certs/fullchain.pem --key=C:/certs/privkey.pem
```

Certificate jis domain/IP ke naam par hai, app mein wahi host use karein.
Example certificate paths placeholders hain; certificate issue/install nahi
kiya gaya hai. Listener public/LAN plain TCP reject karta hai. Agar TLS reverse
proxy hai, woh 9000 ko local 127.0.0.1:9000 tak forward kar sakta hai.

Port pehle se kisi listener ne liya ho to doosra listener usi port par nahi
chalega. Test ke liye --port=9001 use kar sakte hain aur client port bhi badlein.
Background mein chal raha purana listener naye logs tabhi use karega jab restart
hoga. Is terminal ko khula rakhein; band karne ke liye Ctrl+C.

Logs file aur terminal dono mein chahiye to:

```powershell
php artisan gps:listen --host=127.0.0.1 --port=9000 | Tee-Object -FilePath storage/logs/mobile-gps-console.log -Append
```

Web app ke liye alag terminals mein `php artisan queue:work`,
`php artisan reverb:start` aur `php artisan schedule:work` chalayein.
WebSocket immediate updates deta hai; map ka 3-second snapshot fallback bhi hai.

## 3. Mobile app ka flow

1. User GPS/location sharing ki permission deta hai.
2. Stable device identity retain karein: available hardware IMEI (15-digit
   string), warna privately stored installation UUID. Naya UUID har packet par
   nahi banana hai. Packet field ka naam dono cases mein imei hi hai.
3. HTTPS login se token lein. Token tracked person ka hona chahiye.
4. User ko assigned active paid licence number configure karein. Tracker ka
   unrelated licence use nahi chalega. Licence owner aur tracked person alag
   ho sakte hain, lekin licence assigned_tracked_user_id is login user ka ho.
5. TLS socket connect karein, code 01 bhejein aur code 80 acknowledgement padhein.
6. Sharing active ho to GPS fix code 02 har 3-5 seconds bhejein.
7. Code 03 heartbeat har 30 seconds bhejein, GPS fix nahi mil raha ho tab bhi.
8. Network failure par reconnect karein, pending packet ko same packet_id aur
   same data ke saath retry karein. Exponential backoff use karein.
9. Explicit app logout par code 04 bhejein. Yeh token invalidate karega.
10. Socket close/disconnect ko logout na samjhein; token valid ho to reconnect
    kiya ja sakta hai. 90 seconds activity na aane par user offline hota hai.

IMEI normal mobile apps ko har platform par available nahi hota. Installation
UUID supported hai; yeh actual hardware IMEI nahi hai.
[Android identifier rules](https://developer.android.com/identity/user-data-ids).

## 4. HTTPS login alag hai

`POST https://YOUR-API-DOMAIN/api/v1/auth/login`

```json
{
  "email": "tracked-person@example.com",
  "password": "USER_PASSWORD",
  "device_id": "123456789012345",
  "device_name": "My Android Phone",
  "platform": "android"
}
```

Response HTTP 201 mein token aur user aate hain. Isi token ko packet mein use
karein. Agar UUID identity use ki hai to login device_id aur packet imei exact
same UUID honge. Socket code 01 username/password login nahi karta; already
issued token verify karta hai. Local development API currently 8001 par thi,
lekin physical-phone API access ke liye separate reachable HTTPS endpoint
configure karna hoga. API host ko listener host ke saath confuse na karein.

Owned licences ka existing endpoint `GET /api/v1/user-licenses` hai, bearer token
ke saath. Tracker-owned assigned licence tracked user's owned list mein zaroori
nahi dikhe; us case mein assigned number admin/tracker setup se enter/provision
karein. Kisi random number ko generate karke bhejne se licence valid nahi banega.

## 5. Exact frame ka structure

Step order:
JSON object -> compact UTF-8 bytes -> binary header + JSON -> CRC32 ->
poore binary frame ka uppercase/lowercase hex -> ek actual LF newline.

```text
Binary:  [54 47] [01] [CODE] [LENGTH:4 bytes] [JSON:N bytes] [CRC32:4 bytes]
On wire: hex(binary_frame) + actual byte 0A
```

| Binary offset | Bytes | Hex/numeric example | Matlab |
| --- | --- | --- | --- |
| 0 | 1 | 54 hexadecimal = 84 decimal | ASCII T, fixed |
| 1 | 1 | 47 hexadecimal = 71 decimal | ASCII G, fixed |
| 2 | 1 | 01 hexadecimal = 1 decimal | Protocol version |
| 3 | 1 | 02 hexadecimal = 2 decimal | Location message |
| 4-7 | 4 | 0000019A = 410 decimal | JSON UTF-8 byte count |
| 8 onward | N | JSON converted to hex | Actual fields |
| 8+N | 4 | A220EE6D in reference location vector | CRC32 of header + JSON |
| After hex text | 1 wire byte | Actual LF, ASCII 10 | Frame delimiter |

Integers big endian hain: length 410 -> bytes 00 00 01 9A.
Length hex text ki length nahi hai; JSON ke UTF-8 bytes ki length hai.
Poore binary frame ki length N+12 bytes, uske hex ki length 2*(N+12) characters,
aur wire line mein uske baad ek LF hota hai.

Raw binary frame mat bhejein. Sirf JSON ka hex mat bhejein. Header/length/CRC
zaroori hain. Hex ke andar spaces, 0x prefix ya debug labels mat bhejein.
Newline ke badle literal characters backslash+n, ya text "0A", mat bhejein.
TCP packet boundaries reliable nahi hote; server LF tak buffer karta hai.

## 6. Codes: hexadecimal aur decimal alag hain

| Hex code | Decimal | Sender | Meaning |
| --- | --- | --- | --- |
| 01 | 1 | App | Authenticated handshake/status |
| 02 | 2 | App | GPS location + telemetry |
| 03 | 3 | App | Heartbeat/device status |
| 04 | 4 | App | Explicit logout |
| 80 | 128 | Server | Accepted acknowledgement |
| 81 | 129 | Server | Error acknowledgement |

Magic/header version fixed hain. IMEI aur licence dynamic JSON fields hain,
protocol codes nahi. CRC bhi fixed number nahi; har changed payload par badlega.

## 7. Common required fields

| JSON field | Type | Required for | Meaning |
| --- | --- | --- | --- |
| imei | string | 01/02/03/04 | 15-digit IMEI or retained UUID |
| license_number | string | 01/02/03/04 | Actual assigned/owned usable licence |
| token | string | 01/02/03/04 | HTTPS login ka Sanctum token |
| packet_id | UUID string | 01/02/03/04 | Unique per new packet, same on retry |
| recorded_at | ISO-8601 date string | 01/02/03/04 | UTC event time, e.g. current instant |

Server clock se 5 minutes se purana ya 30 seconds se zyada future timestamp
reject hoga. Guide ke example timestamps ko actual app mein hard-code na karein.

## 8. Location code 02 fields

| Field | Required? | Units/range |
| --- | --- | --- |
| source_type | Yes | android, ios, browser, desktop or api |
| latitude | Yes | Decimal degrees, -90 to 90 |
| longitude | Yes | Decimal degrees, -180 to 180 |
| accuracy | Optional | Meters, >=0 |
| speed | Optional | Meters/second, >=0; km/h / 3.6 karke bhejein |
| bearing | Optional | Travel direction degrees 0..360 |
| heading | Optional | Heading degrees 0..360 |
| altitude | Optional | Meters, negative allowed |
| battery_level | Optional | Integer percentage 0..100 |
| network_type | Optional | String <=20 characters, e.g. 4g/wifi |
| signal_strength | Optional | Normalized integer percentage 0..100, raw dBm nahi |
| provider | Optional | String <=30 characters, e.g. gps |
| is_mock | Optional | JSON true/false |
| tracking_session_id | Optional | Previous ack ki tracking session UUID |

Unknown/missing optional readings omit ya null karein; unknown speed ko fake zero
na banayein. Bearing 0 north, 90 east, 180 south, 270 west ke convention mein
bhejein. IMEI leading zeros bachane ke liye hamesha string rahega.

## 9. Handshake aur heartbeat data

Common fields + optional battery_level, network_type, is_gps_enabled.
Code 03 ke liye latitude/longitude required nahi hain. Heartbeat se presence aur
device status update hota hai, GPS history row nahi banti. Location code 02 bhejne
se server GPS enabled aur internet available samajhta hai.

## 10. Exact reference packets

Neeche ke vectors backend ke actual HexPacketCodec se generate hue hain.
Token/licence dummy hain: format/CRC comparison ke liye, real authenticated
tracking ke liye nahi. Values ya JSON order/whitespace badlenge to length/CRC/hex
ko dobara generate karein. Hex strings ke end par actual LF append karna hai.

### handshake (code 01)

JSON:
```json
{
  "imei": "123456789012345",
  "license_number": "DEMO-LICENSE",
  "token": "DEMO-TOKEN-NOT-A-REAL-CREDENTIAL",
  "packet_id": "2d222222-3333-4444-8555-000000000001",
  "recorded_at": "2026-10-03T12:00:00Z",
  "battery_level": 85,
  "network_type": "4g",
  "is_gps_enabled": true
}
```

Payload bytes: 250; header: 54470101000000FA; CRC32: 3D5822B3.

Complete hex without trailing LF:
```text
54470101000000FA7B22696D6569223A22313233343536373839303132333435222C226C6963656E73655F6E756D626572223A2244454D4F2D4C4943454E5345222C22746F6B656E223A2244454D4F2D544F4B454E2D4E4F542D412D5245414C2D43524544454E5449414C222C227061636B65745F6964223A2232643232323232322D333333332D343434342D383535352D303030303030303030303031222C227265636F726465645F6174223A22323032362D31302D30335431323A30303A30305A222C22626174746572795F6C6576656C223A38352C226E6574776F726B5F74797065223A223467222C2269735F6770735F656E61626C6564223A747275657D3D5822B3
```

### location (code 02)

JSON:
```json
{
  "imei": "123456789012345",
  "license_number": "DEMO-LICENSE",
  "token": "DEMO-TOKEN-NOT-A-REAL-CREDENTIAL",
  "packet_id": "2d222222-3333-4444-8555-000000000002",
  "recorded_at": "2026-10-03T12:00:00Z",
  "source_type": "android",
  "latitude": 28.6139,
  "longitude": 77.209,
  "accuracy": 8,
  "speed": 4.2,
  "bearing": 90,
  "heading": 90,
  "altitude": 220,
  "battery_level": 85,
  "network_type": "4g",
  "signal_strength": 70,
  "provider": "gps",
  "is_mock": false
}
```

Payload bytes: 410; header: 544701020000019A; CRC32: A220EE6D.

Complete hex without trailing LF:
```text
544701020000019A7B22696D6569223A22313233343536373839303132333435222C226C6963656E73655F6E756D626572223A2244454D4F2D4C4943454E5345222C22746F6B656E223A2244454D4F2D544F4B454E2D4E4F542D412D5245414C2D43524544454E5449414C222C227061636B65745F6964223A2232643232323232322D333333332D343434342D383535352D303030303030303030303032222C227265636F726465645F6174223A22323032362D31302D30335431323A30303A30305A222C22736F757263655F74797065223A22616E64726F6964222C226C61746974756465223A32382E363133392C226C6F6E676974756465223A37372E3230392C226163637572616379223A382C227370656564223A342E322C2262656172696E67223A39302C2268656164696E67223A39302C22616C746974756465223A3232302C22626174746572795F6C6576656C223A38352C226E6574776F726B5F74797065223A223467222C227369676E616C5F737472656E677468223A37302C2270726F7669646572223A22677073222C2269735F6D6F636B223A66616C73657DA220EE6D
```

### heartbeat (code 03)

JSON:
```json
{
  "imei": "123456789012345",
  "license_number": "DEMO-LICENSE",
  "token": "DEMO-TOKEN-NOT-A-REAL-CREDENTIAL",
  "packet_id": "2d222222-3333-4444-8555-000000000003",
  "recorded_at": "2026-10-03T12:00:00Z",
  "battery_level": 85,
  "network_type": "4g",
  "is_gps_enabled": true
}
```

Payload bytes: 250; header: 54470103000000FA; CRC32: 5E956C62.

Complete hex without trailing LF:
```text
54470103000000FA7B22696D6569223A22313233343536373839303132333435222C226C6963656E73655F6E756D626572223A2244454D4F2D4C4943454E5345222C22746F6B656E223A2244454D4F2D544F4B454E2D4E4F542D412D5245414C2D43524544454E5449414C222C227061636B65745F6964223A2232643232323232322D333333332D343434342D383535352D303030303030303030303033222C227265636F726465645F6174223A22323032362D31302D30335431323A30303A30305A222C22626174746572795F6C6576656C223A38352C226E6574776F726B5F74797065223A223467222C2269735F6770735F656E61626C6564223A747275657D5E956C62
```

### logout (code 04)

JSON:
```json
{
  "imei": "123456789012345",
  "license_number": "DEMO-LICENSE",
  "token": "DEMO-TOKEN-NOT-A-REAL-CREDENTIAL",
  "packet_id": "2d222222-3333-4444-8555-000000000004",
  "recorded_at": "2026-10-03T12:00:00Z"
}
```

Payload bytes: 189; header: 54470104000000BD; CRC32: 962FB2F8.

Complete hex without trailing LF:
```text
54470104000000BD7B22696D6569223A22313233343536373839303132333435222C226C6963656E73655F6E756D626572223A2244454D4F2D4C4943454E5345222C22746F6B656E223A2244454D4F2D544F4B454E2D4E4F542D412D5245414C2D43524544454E5449414C222C227061636B65745F6964223A2232643232323232322D333333332D343434342D383535352D303030303030303030303034222C227265636F726465645F6174223A22323032362D31302D30335431323A30303A30305A227D962FB2F8
```


Vectors dobara generate/check karne ke liye:
```powershell
php tools/mobile-gps-vectors.php
```
Har vector ka roundtrip_ok true hona chahiye.

## 11. Server acknowledgement ka matlab

Code 80 ka JSON decoded hone par example:

```json
{
  "packet_id": "2d222222-3333-4444-8555-000000000002",
  "ok": true,
  "code": "02",
  "server_time": "2026-10-03T12:00:00Z",
  "distance_filter_meters": 100,
  "saved": false,
  "live_cached": true,
  "reason": "throttled",
  "tracking_session_id": null
}
```

saved=true: history mein point save hua.
live_cached=true: isi fix ki live position cache mein mili.
saved=false + live_cached=true: point live map ke liye update hua, history mein
nahi; configured distance radius cross nahi hua. Isko failed send mat samjhein.
duplicate=true: original ack replay hui; retry ne naya point create nahi kiya.
Duplicate reply ke saved/live_cached flags original processing ka result hain,
current cache-state ka fresh check nahi.

Code 81 error:
```json
{"ok":false,"reason":"invalid_checksum"}
```

Errors:
- invalid_hex/header/length/checksum/json/payload: packet construction fix karein.
- unknown_code/invalid_fields: code, required fields, ranges ya clock check karein.
- unauthorized: login/token/device identity/active user check karein.
- device_revoked: device session revoked hai; authorized login dobara chahiye.
- invalid_license: assigned licence active, paid, unexpired hai ya nahi check karein.
- packet_id_conflict: same ID par different validated payload bheja gaya.
- rate_limited: backoff karein; current source-IP limit 120 packets/minute hai.
- server_error: same packet retry karein; operational server issue check karein.

Ek time par ek unacknowledged packet rakhein; malformed-frame error mein trustworthy
packet_id nahi milta. Logout code 04 token delete karta hai, isliye same token se
uska retry unauthorized ho sakta hai. Lost logout ack par next session ke liye
fresh HTTPS login karein. Ack ko bhi wahi header/length/CRC rules se decode karein.

## 12. Terminal mein kya dikhna chahiye?

Logs JSON lines hain, UTC time ke saath:
- CONNECTED: TCP connection accept hua.
- RECEIVED: complete LF-delimited hex frame aaya; abhi validation baaki ho sakti hai.
- HANDSHAKE: code 01 authenticated.
- SAVED: code 02 history write accepted; live_cached flag bhi dekhein.
- LIVE_CACHED: live fix updated, radius ke kaaran history point nahi save hua.
- LOCATION_NOT_SAVED: location authenticated, lekin stale/outdated ya no cache update.
- HEARTBEAT: status/activity update, no history point.
- DUPLICATE: retry detected, naya point nahi bana.
- LOGOUT: session end hua.
- REJECTED: frame/auth/licence reject hua; reason log mein hai.
- DISCONNECTED: socket close hua.

Log mein peer, code, IMEI, licence, packet ID, recorded time, location/telemetry,
save/cache flags, reason aur radius dikhte hain. Token, password, raw hex credential
packet aur unknown fields print nahi hote. Console location/licence logs private
operator data hain; publicly share na karein.

Example:
```json
{"event":"RECEIVED","peer":"192.168.1.70","hex_characters":844}
{"event":"LIVE_CACHED","code":"02","imei":"123456789012345","license_number":"DEMO-LICENSE","latitude":28.6139,"longitude":77.209,"saved":false,"live_cached":true,"reason":"throttled","distance_filter_meters":100}
```

## 13. Mobile implementation references

- Android Kotlin frame encoder, CRC decoder aur verified TLS sender:
  [MobileGpsProtocol.kt](examples/MobileGpsProtocol.kt).
- PHP local test sender: [mobile-gps-client.php](../tools/mobile-gps-client.php).
- Server technical specification: [mobile-gps-protocol.md](mobile-gps-protocol.md).

Kotlin usage: JSON object mein common/location fields set karein, phir background
IO coroutine/executor se `MobileGpsProtocol.send(host, 9000, 0x02, payload)`
call karein. Return pair ka first value 128 success ya 129 error hai. Token ko
Android private secure storage mein rakhein. Sample transport login screen,
GPS background service, permission UX aur app lifecycle implement nahi karta.

App ko registration/login, licence setup, permission request, explicit sharing
toggle, GPS collection, heartbeat timer, pending-packet retry queue aur
network/reconnection state implement karni hogi. Offline historical GPS records
ke liye HTTPS `POST /api/v1/gps/locations/batch` available hai; expired live
timestamps ko socket par baar-baar retry na karein.

