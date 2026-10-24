// A small, dependency-free PDF writer for paginated GPS reports. Coordinates use PDF points.
const ascii = value => String(value ?? '-').normalize('NFKD').replace(/[^\x20-\x7e]/g, '?');
const literal = value => ascii(value).replace(/[\\()]/g, '\\$&');
export function historyPdf({title, range, route, alerts, summary, points = [], truncated = false}) {
    const pages = [];
    let ops, y;
    const text = (value, x, top, size = 9, color = '0.12 0.20 0.28') => ops.push(`BT /F1 ${size} Tf ${color} rg ${x} ${842 - top} Td (${literal(value)}) Tj ET`);
    const newPage = () => {
        ops = []; pages.push(ops); y = 120;
        ops.push('0.04 0.30 0.35 rg 0 766 595 76 re f');
        text('TRACKER ENTERPRISE  /  GPS HISTORY', 32, 30, 10, '1 1 1');
        text(title, 32, 54, 17, '1 1 1');
        text(range, 32, 98, 8);
    };
    const line = value => { if (y > 790) newPage(); text(value, 32, y); y += 17; };
    const section = value => { if (y > 745) newPage(); y += 12; text(value, 32, y, 13, '0.04 0.42 0.46'); y += 24; };
    const table = (heading, columns, widths, rows) => {
        section(heading);
        const header = () => { let x = 32; columns.forEach((v, i) => {text(v, x, y, 8); x += widths[i];}); y += 20; };
        header();
        if (!rows.length) line('No records in this period.');
        for (const row of rows) {
            if (y > 785) {newPage(); section(heading + ' (continued)'); header();}
            let x = 32;
            row.forEach((v, i) => {text(v, x, y, 8); x += widths[i];});
            ops.push(`0.90 0.93 0.95 RG 32 ${842-y-6} m 563 ${842-y-6} l S`); y += 22;
        }
    };
    newPage();
    if (summary) {
        section('Journey overview');
        line(`${summary.points} GPS records    |    Maximum speed: ${summary.max_speed ?? '-'} km/h`);
        line('All timestamps are Indian Standard Time (UTC +05:30).');
    }
    if (points.length) {
        section('Route overview - GPS trace');
        line('Schematic GPS trace; long gaps and tracker changes are disconnected.');
        if (truncated) line('Trace contains first 5,000 points; the tables contain all report records.');
        const minLat = Math.min(...points.map(p => p.latitude)), maxLat = Math.max(...points.map(p => p.latitude));
        const minLng = Math.min(...points.map(p => p.longitude)), maxLng = Math.max(...points.map(p => p.longitude));
        const cos = Math.max(.01, Math.cos((minLat + maxLat) / 2 * Math.PI / 180));
        const scale = Math.min(500 / (Math.max(.00001, maxLng - minLng) * cos), 160 / Math.max(.00001, maxLat - minLat));
        const xy = p => [45 + (p.longitude - minLng) * cos * scale, 842 - y - 170 + (p.latitude - minLat) * scale];
        for (let i = 1; i < points.length; i++) {
            const a = points[i - 1], b = points[i];
            if (a.gps_device_id !== b.gps_device_id || Date.parse(b.recorded_at) - Date.parse(a.recorded_at) > 300000) continue;
            const [x1, y1] = xy(a), [x2, y2] = xy(b);
            const color = b.speed == null ? '0.58 0.64 0.72' : b.speed > 80 ? '0.96 0.62 0.04' : b.speed > 40 ? '0.23 0.51 0.96' : '0.06 0.72 0.51';
            ops.push(`${color} RG 1.5 w ${x1.toFixed(2)} ${y1.toFixed(2)} m ${x2.toFixed(2)} ${y2.toFixed(2)} l S`);
        }
        y += 188;
        line('Green: 0-40 km/h | Blue: 41-80 km/h | Amber: >80 km/h | Grey: unavailable');
    }
    if (route) table('GPS activity log', ['GPS time (IST)', 'Latitude', 'Longitude', 'km/h', 'Ignition'], [185, 95, 95, 65, 70], route);
    if (alerts) table('Geofence alerts', ['Geofence', 'Alert', 'Started (IST)', 'Ended (IST)', 'Speed / limit'], [90, 90, 135, 135, 70], alerts.map(r => [ascii(r[0]).slice(0, 18), ascii(r[1]).slice(0, 18), ...r.slice(2)]));
    pages.forEach((page, i) => {ops = page; text(`Tracker Enterprise | Generated report | Page ${i + 1} of ${pages.length}`, 32, 820, 8);});
    const objects = ['<< /Type /Catalog /Pages 2 0 R >>', '', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    const refs = [];
    for (const page of pages) {
        const id = objects.length + 1, stream = page.join('\n'); refs.push(`${id} 0 R`);
        objects.push(`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ${id + 1} 0 R >>`);
        objects.push(`<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`);
    }
    objects[1] = `<< /Type /Pages /Kids [${refs.join(' ')}] /Count ${pages.length} >>`;
    let pdf = '%PDF-1.4\n'; const offsets = [0];
    objects.forEach((o, i) => { offsets.push(pdf.length); pdf += `${i + 1} 0 obj\n${o}\nendobj\n`; });
    const start = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    pdf += offsets.slice(1).map(offset => `${String(offset).padStart(10, '0')} 00000 n \n`).join('');
    return pdf + `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${start}\n%%EOF`;
}
