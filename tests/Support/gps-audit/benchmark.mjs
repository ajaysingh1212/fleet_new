// Real TCP -> database queues -> isolated Reverb -> real dashboard -> Leaflet.
// Requires: npm install --prefix tmp/gps-audit-tools --no-save playwright
import {spawn} from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import http from 'node:http';
import {randomBytes} from 'node:crypto';
import {createRequire} from 'node:module';
const root = process.cwd(), require = createRequire(path.join(root, 'tmp/gps-audit-tools/package.json'));
const {chromium} = require('playwright');
const run = path.join(root, 'tmp', `gps-audit-${Date.now()}`);
for (const part of ['', 'storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs']) fs.mkdirSync(path.join(run, part), {recursive:true});
fs.writeFileSync(path.join(run, 'audit.sqlite'), '');
const mysql = !process.argv.includes('--sqlite');
const database = mysql ? `fleet_gps_audit_${randomBytes(8).toString('hex')}` : path.join(run,'audit.sqlite');
if(mysql)fs.writeFileSync(path.join(run,'db-name'),database);
const ports = {web: 18091, ws: 18089, tcp: 19005, vite: 15179};
const env = {...process.env, APP_ENV:'testing', APP_DEBUG:'false', APP_URL:`http://127.0.0.1:${ports.web}`,
    APP_KEY:`base64:${randomBytes(32).toString('base64')}`, DB_CONNECTION:mysql?'mysql':'sqlite', DB_DATABASE:database, DB_URL:'',
    DB_BUSY_TIMEOUT:'5000', DB_JOURNAL_MODE:'WAL', QUEUE_CONNECTION:'database', CACHE_STORE:'file', SESSION_DRIVER:'file',
    LARAVEL_STORAGE_PATH:path.join(run,'storage'), APP_CONFIG_CACHE:path.join(run,'config.php'), APP_ROUTES_CACHE:path.join(run,'routes.php'),
    VIEW_COMPILED_PATH:path.join(run,'storage/framework/views'), MAIL_MAILER:'log', GPS_AUDIT_RUN:run,
    GPS_LIVE_FIRST:'true', GPS_LIVE_CACHE_STORE:'file', GPS_TRACE_ENABLED:'true', GPS_LAST_SEEN_SECONDS:'180',
    GPS_SOCKET_IDLE_SECONDS:'0', GPS_TRACE_PATH:path.join(run,'storage/logs/gps-pipeline.log'),
    REVERB_APP_ID:'isolated-audit', REVERB_APP_KEY:'isolated-audit-key', REVERB_APP_SECRET:randomBytes(24).toString('hex'),
    REVERB_HOST:'127.0.0.1', REVERB_PORT:String(ports.ws), REVERB_SCHEME:'http', REVERB_SERVER_HOST:'127.0.0.1', REVERB_SERVER_PORT:String(ports.ws),
    VITE_REVERB_APP_KEY:'isolated-audit-key', VITE_REVERB_HOST:'127.0.0.1', VITE_REVERB_PORT:String(ports.ws), VITE_REVERB_SCHEME:'http',
    BROADCAST_CONNECTION:'reverb', GPS_AUDIT_VITE_PORT:String(ports.vite), GPS_AUDIT_HOT_FILE:path.join(run,'vite.hot')};
const children = [], logs = [], sockets = [], errors = [], ackMs = [];
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
function start(name, command, args) {
    const fd = fs.openSync(path.join(run, `${name}.log`),'a'); logs.push(fd);
    const child = spawn(command, args, {cwd:root, env, windowsHide:true, stdio:['ignore',fd,fd]});
    fs.appendFileSync(path.join(run,'processes.jsonl'),JSON.stringify({pid:child.pid,name,args})+'\n');
    children.push(child); return child;
}
async function command(name, args) {
    const child = start(name, 'php', args);
    const code = await new Promise((resolve,reject) => {child.once('error',reject);child.once('exit',resolve);});
    if (code) throw new Error(`${name} failed; inspect ${name}.log in ${run}`);
    return fs.readFileSync(path.join(run,`${name}.log`),'utf8');
}
async function waitFor(fn, timeout=20000) {
    const until=Date.now()+timeout; while(Date.now()<until) {try {const value=await fn();if(value)return value;}catch{} await pause(100);}
    throw new Error('Timed out waiting for isolated service/test condition.');
}
async function freePort(port) {
    await new Promise((resolve,reject) => {const server=net.createServer();server.once('error',reject);server.listen(port,'127.0.0.1',()=>server.close(resolve));});
}
function crc(data) {let c=0xffff;for(const b of data){c^=b;for(let k=0;k<8;k++)c=(c&1)?(c>>>1)^0x8408:c>>>1;}return (~c)&0xffff;}
function frame(type,payload,serial) {
    const body=Buffer.concat([Buffer.from([payload.length+5,type]),payload,Buffer.from([serial>>>8,serial&255])]);
    const check=Buffer.alloc(2);check.writeUInt16BE(crc(body));return Buffer.concat([Buffer.from([0x78,0x78]),body,check,Buffer.from([13,10])]);
}
function location(i,n,serial) {
    const d=new Date(), b=Buffer.alloc(29);b.set([d.getUTCFullYear()-2000,d.getUTCMonth()+1,d.getUTCDate(),d.getUTCHours(),d.getUTCMinutes(),d.getUTCSeconds(),0xcc]);
    b.writeUInt32BE(Math.round((25.6+i*.01+n*.0001)*1800000),7);b.writeUInt32BE(Math.round((85.1+i*.01+n*.0001)*1800000),11);
    b[15]=21;b.writeUInt16BE(0x1400|90,16);b[26]=1;return frame(0x22,b,serial);
}
function connect(i) {
    return new Promise((resolve,reject) => {
        const socket=net.createConnection({host:'127.0.0.1',port:ports.tcp});sockets.push(socket);
        socket.on('error',reject); socket.buffer=Buffer.alloc(0);socket.waiters=[];
        socket.on('data',chunk=>{socket.buffer=Buffer.concat([socket.buffer,chunk]);while(socket.buffer.length>=10){const a=socket.buffer.subarray(0,10);socket.buffer=socket.buffer.subarray(10);const waiter=socket.waiters.shift();if(waiter){ackMs.push(performance.now()-waiter.start);waiter.resolve(a);}}});
        socket.once('connect',async()=>{
            try {const login=frame(1,Buffer.from('0'+String(990000000000000+i),'hex'),1);
                await send(socket,login,true); resolve({socket,login});}catch(e){reject(e);}
        });
    });
}
function send(socket, bytes, fragment=false) {
    return new Promise((resolve,reject)=>{
        const timer=setTimeout(()=>reject(new Error('Protocol ACK timeout')),10000);
        socket.waiters.push({start:performance.now(),resolve:a=>{clearTimeout(timer);resolve(a);}});
        if(fragment){socket.write(bytes.subarray(0,3));setTimeout(()=>socket.write(bytes.subarray(3)),10);}else socket.write(bytes);
    });
}
const quantiles = values => {const v=[...values].filter(Number.isFinite).sort((a,b)=>a-b);return {count:v.length,p50:v[Math.ceil(v.length*.5)-1]??null,p95:v[Math.ceil(v.length*.95)-1]??null,max:v.at(-1)??null};};
let browser;
let reverb;
let created = false;
let peakPending=0,peakWorkers=0;
const poolTimer=setInterval(()=>{try{const state=JSON.parse(fs.readFileSync(path.join(run,'storage/framework/gps-workers-status.json'),'utf8'));peakPending=Math.max(peakPending,state.queues.reduce((n,q)=>n+q.pending,0));peakWorkers=Math.max(peakWorkers,state.queues.reduce((n,q)=>n+q.running,0));}catch{}},100);
try {
    for(const port of Object.values(ports)) await freePort(port);
    if(mysql){await command('create',['tests/Support/gps-audit/seed.php','create']);created=true;}
    await command('migrate',['artisan','migrate','--force','--no-interaction']);
    await command('seed',['tests/Support/gps-audit/seed.php']);
    reverb=start('reverb','php',['artisan','reverb:start','--host=127.0.0.1',`--port=${ports.ws}`]);
    start('workers','php',['artisan','gps:workers','--max=4','--interval=1','--jobs-per-worker=20']);
    start('receiver','php',['artisan','gps:listen','--host=127.0.0.1',`--port=${ports.tcp}`,'--diagnostic','--persist','--registered','--format=json']);
    start('vite','node',[path.join(root,'node_modules/vite/bin/vite.js'),'--host=127.0.0.1','--strictPort']);
    start('web','php',['-S',`127.0.0.1:${ports.web}`,'-t','public','tests/Support/gps-audit/router.php']);
    await waitFor(()=>fs.existsSync(path.join(run,'vite.hot')));
    await waitFor(()=>new Promise(resolve=>{const request=http.get(`http://127.0.0.1:${ports.web}/login`,response=>{response.resume();resolve(response.statusCode===200);});request.on('error',()=>resolve(false));request.setTimeout(1000,()=>{request.destroy();resolve(false);});}));
    browser=await chromium.launch({channel:'chrome',headless:true});
    const page=await browser.newPage({viewport:{width:1500,height:950}});
    page.on('pageerror',e=>errors.push({message:String(e.message),page:page.url(),stack:String(e.stack??'')}));
    await page.goto(`http://127.0.0.1:${ports.web}/login`);
    await page.locator('#email').fill('gps-audit@example.invalid');await page.locator('#password').fill('Isolated-audit-password-7391');
    await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);
    await waitFor(()=>page.evaluate(()=>window.Echo?.connector?.pusher?.connection?.state==='connected' && document.querySelector('[data-dashboard-stat=total]')?.textContent==='3'));
    const stage=await page.locator('#fleet-dashboard').boundingBox();
    const ui={stage_height:stage.height,viewport_height:950,drawers_closed:await page.locator('.offcanvas.show').count()===0};
    await page.locator('#fleet-vehicles-button').click();await page.locator('#fleet-vehicles-drawer.show').waitFor();
    ui.vehicles_drawer_opens=true;await page.getByRole('button',{name:'Close vehicle list',exact:true}).click();
    await page.locator('#dashboard-information-button').click();await page.locator('#dashboard-information-tab.show').waitFor();ui.information_drawer_opens=true;
    await page.getByRole('button',{name:'Close information and activity',exact:true}).click();
    const devices=await Promise.all([1,2,3].map(connect));
    // Repeated same-IMEI login is legal; no socket reassociation or dropped ACK.
    await Promise.all(devices.map(d=>send(d.socket,d.login)));
    let locations=0;
    for(let n=0;n<15;n++) {
        await Promise.all(devices.map((d,i)=>send(d.socket,location(i,n,10+n))));locations+=3;await pause(1000);
    }
    await page.screenshot({path:path.join(run,'dashboard.png')});
    // A single burst >64 valid frames must not close the socket or lose ACKs.
    const device=devices[0].socket, burst=[];
    for(let n=0;n<180;n++)burst.push(frame(0x13,Buffer.from([0x46,4,4,0,1]),100+n));
    const otherVehicles=(async()=>{await pause(500);for(let n=16;n<=17;n++){await Promise.all(devices.slice(1).map((d,i)=>send(d.socket,location(i+1,n,500+n))));locations+=2;await pause(1000);}})();
    await Promise.all([Promise.all(burst.map(bytes=>send(device,bytes))),otherVehicles]);
    await pause(1500);
    await send(device,location(0,20,400));locations++;
    await pause(2000);
    // Real broker interruption: the latest-state API must recover a fix even
    // without another GPS packet. No production Reverb process is touched.
    await page.locator('#fleet-vehicles-button').click();
    await page.locator('#dashboard-fleet-roster button').filter({hasText:'990000000000001'}).click();
    reverb.kill();await waitFor(()=>reverb.exitCode!==null||reverb.signalCode!==null,5000);
    await pause(1000);
    const recoveryStarted=performance.now();
    await send(device,location(0,21,600));locations++;
    await waitFor(async()=> (await page.locator('#fleet-vehicle-detail').textContent()).includes('25.602100'),5000);
    const recoveryMs=performance.now()-recoveryStarted;
    reverb=start('reverb-restarted','php',['artisan','reverb:start','--host=127.0.0.1',`--port=${ports.ws}`]);
    await waitFor(()=>page.evaluate(()=>window.Echo.connector.pusher.connection.state==='connected'),20000);
    const metrics=await page.evaluate(()=>window.__gpsPipelineMetrics?.samples??[]);
    const statuses=[];
    await waitFor(async()=>{
        const raw=await command('status-'+statuses.length,['tests/Support/gps-audit/seed.php','status']);
        const state=JSON.parse(raw);statuses.push(state);return state.jobs===0&&state.history===locations;
    },30000);
    const final=statuses.at(-1), recv=final.events.filter(e=>e.phase==='packet_received').length;
    const result={environment:`isolated ${mysql?'MySQL/MariaDB':'SQLite/WAL'} + database queues + Reverb + headless Chrome; same-host clocks`,
        test_devices:3,locations_sent:locations,history_locations:final.history,history_loss:locations-final.history,
        unexpected_disconnects:final.events.filter(e=>e.phase==='socket_closed').length,
        ack_roundtrip_ms:quantiles(ackMs),receiver_to_ack_ms:quantiles(final.events.filter(e=>e.phase==='ack_sent').map(e=>e.duration_ms)),
        queue_delay_ms:quantiles(final.events.filter(e=>e.phase==='latest_projected').map(e=>e.queue_delay_ms)),
        server_to_browser_ms:quantiles(metrics.map(e=>e.server_to_browser_ms)),server_to_first_marker_frame_ms:quantiles(metrics.map(e=>e.server_to_marker_ms)),
        browser_event_to_marker_ms:quantiles(metrics.map(e=>e.event_to_marker_ms)),
        pending_after_drain:final.jobs,peak_observed_pending:peakPending,peak_observed_workers:peakWorkers,
        failed_jobs:final.failed,received_frames:recv,expected_frames:locations+6+180,
        websocket_recovery_without_new_gps_ms:recoveryMs,ui,browser_errors:errors.filter(e=>e.page.includes('/dashboard')),
        other_page_errors:errors.filter(e=>!e.page.includes('/dashboard')),
        note:'Broadcast coalescing is intentional. Marker metrics count rendered new GPS timestamps, not every heartbeat/history packet.'};
    fs.writeFileSync(path.join(run,'results.json'),JSON.stringify(result,null,2));
    console.log(JSON.stringify({run,...result},null,2));
    if(result.history_loss||result.unexpected_disconnects||result.failed_jobs||result.browser_errors.length||!metrics.length)process.exitCode=1;
} catch(e) {console.error(`Audit benchmark failed: ${e.message}\nPrivate diagnostic artifacts: ${run}`);process.exitCode=1;}
finally {
    clearInterval(poolTimer);
    for(const s of sockets)s.destroy();if(browser)await browser.close();
    const poolProcess=children.find(child=>fs.readFileSync(path.join(run,'processes.jsonl'),'utf8').includes(`"pid":${child.pid},"name":"workers"`));
    if(poolProcess?.exitCode===null){await command('stop-pool',['artisan','gps:workers','--stop']);try{await waitFor(()=>poolProcess.exitCode!==null,15000);}catch{}}
    for(const child of children.reverse())if(child.exitCode===null)child.kill();
    if(created)await command('drop',['tests/Support/gps-audit/seed.php','drop']);
    for(const fd of logs)try{fs.closeSync(fd);}catch{}
}
