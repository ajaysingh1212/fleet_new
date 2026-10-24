import './bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';
if (document.getElementById('vf-map')) {
    const $ = id => document.getElementById(id), api='/admin/vehicle-geofences';
    const map=L.map('vf-map').setView([20.59,78.96],5), shapes=L.featureGroup().addTo(map), draft=L.featureGroup().addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
    let drawing, geometry, editing=null, vehiclePage=1, zonePage=1, historyPage=1, searchTimer, vehicleRequest=0, fitted=false, locateRequest=0;const vehicleIndex=new Map();
    const error=e=>{ $('vf-errors').textContent=Object.values(e.response?.data?.errors??{}).flat().join('\n')||e.message||'Could not save. Please retry.'; };
    const text=(parent,tag,value)=>{const el=document.createElement(tag);el.textContent=value;parent.append(el);return el;};
    const date=v=>v?new Date(v.replace(' ','T')+(v.endsWith('Z')||v.includes('+')?'':'Z')).toLocaleString():'Active';
    async function vehicles(selected=null){
        const version=++vehicleRequest;
        try{ const {data}=await window.axios.get(api+'/vehicles',{params:{q:$('vf-vehicle-search').value,page:vehiclePage}});if(version!==vehicleRequest)return;
            $('vf-vehicle').replaceChildren(new Option('Choose a vehicle',''));
            data.data.forEach(v=>$('vf-vehicle').append(new Option(`${v.registration_number} · ${v.name}`,v.id)));
            if(selected&&!data.data.some(v=>v.id===selected.vehicle_id))$('vf-vehicle').append(new Option(selected.registration,selected.vehicle_id));
            if(selected){$('vf-vehicle').value=selected.vehicle_id;vehicleIndex.set(selected.vehicle_id,{registration_number:selected.registration});}
            $('vf-vehicles-prev').disabled=vehiclePage===1;$('vf-vehicles-next').disabled=!data.next_page_url;
        }catch(e){error(e);}
    }
    function layer(z){ return z.type==='circle'?L.circle([z.center_lat,z.center_lng],{radius:z.radius_meters,color:'#2563eb'}):L.polygon(z.points.map(p=>[p.latitude,p.longitude]),{color:'#2563eb'}); }
    async function zones(){try{const{data}=await window.axios.get(api,{params:{page:zonePage}});shapes.clearLayers();$('vf-zones').replaceChildren();
        for(const z of data.data){const shape=layer(z).addTo(shapes);if(z.status!=='active')shape.setStyle({color:'#94a3b8',dashArray:'5,6'});shape.bindTooltip(z.name);const card=text($('vf-zones'),'article','');card.className='vf-zone';text(card,'strong',z.name);text(card,'div',z.registration);text(card,'small',`${z.min_speed_kmh??'—'} – ${z.max_speed_kmh??'—'} km/h`);
            const pause=text(card,'button',z.status==='active'?'Pause':'Resume');pause.type='button';pause.onclick=async()=>{try{await window.axios.patch(api+'/'+z.rule_id+'/status',{active:z.status!=='active'});zones();history();}catch(e){error(e);}};const edit=text(card,'button','Edit zone');edit.type='button';edit.onclick=()=>{editing=z;geometry=z;$('vf-name').value=z.name;$('vf-min').value=z.min_speed_kmh??'';$('vf-max').value=z.max_speed_kmh??'';vehicles(z);draft.clearLayers();layer(z).addTo(draft);map.fitBounds(shape.getBounds());$('vf-shape-status').textContent='Editing zone. Draw again to replace its boundary.';};}
        if(!fitted&&shapes.getLayers().length){map.fitBounds(shapes.getBounds(),{padding:[35,35],maxZoom:16});fitted=true;}$('vf-zones-prev').disabled=zonePage===1;$('vf-zones-next').disabled=zonePage>=data.last_page;
    }catch(e){error(e);}}
    async function history(){try{const{data}=await window.axios.get(api+'/alerts',{params:{page:historyPage}});$('vf-history').replaceChildren();
        for(const a of data.data){const row=text($('vf-history'),'article','');text(row,'strong',`${a.registration_number} · ${a.type}`);text(row,'div',a.geofence_name);text(row,'div',`${date(a.started_at)} → ${date(a.ended_at)}`);text(row,'small',`${a.speed??'—'} km/h${a.limit!==null?' · limit '+a.limit:''}`);}
        if(!data.data.length)text($('vf-history'),'p','No violations recorded yet.');$('vf-history-prev').disabled=historyPage===1;$('vf-history-next').disabled=!data.next_page_url;
    }catch(e){error(e);}}
    document.querySelectorAll('[data-vf-draw]').forEach(b=>b.onclick=()=>{drawing?.disable();const type=b.dataset.vfDraw;drawing=new L.Draw[{circle:'Circle',polygon:'Polygon',rectangle:'Rectangle'}[type]](map,{allowIntersection:false});drawing.enable();});
    map.on(L.Draw.Event.CREATED,e=>{draft.clearLayers();draft.addLayer(e.layer);geometry=e.layerType==='circle'?{type:'circle',center_lat:e.layer.getLatLng().lat,center_lng:e.layer.getLatLng().lng,radius_meters:Math.round(e.layer.getRadius())}:{type:e.layerType,points:e.layer.getLatLngs()[0].map(p=>({latitude:p.lat,longitude:p.lng}))};$('vf-shape-status').textContent='Boundary ready. Save to start monitoring.';});
    $('vf-form').onsubmit=async e=>{e.preventDefault();$('vf-errors').textContent='';if(!geometry){$('vf-errors').textContent='Draw a boundary first.';return;}const button=e.submitter??$('vf-form').querySelector('[type=submit]');button.disabled=true;try{const payload={...geometry,name:$('vf-name').value,category:'custom',color:'#2563eb',vehicle_id:Number($('vf-vehicle').value),min_speed_kmh:$('vf-min').value===''?null:Number($('vf-min').value),max_speed_kmh:$('vf-max').value===''?null:Number($('vf-max').value)};
        await window.axios[editing?'put':'post'](api+(editing?'/'+editing.rule_id:''),payload);$('vf-shape-status').textContent='Saved. Vehicle zone settings updated.';draft.clearLayers();editing=null;geometry=null;zones();history();}catch(e){error(e);}finally{button.disabled=false;}};
    $('vf-reset').onclick=()=>{editing=null;geometry=null;draft.clearLayers();$('vf-form').reset();$('vf-shape-status').textContent='Choose a shape, then draw it on the map.';};
    $('vf-vehicle').onchange=async()=>{const version=++locateRequest, id=Number($('vf-vehicle').value), vehicle=vehicleIndex.get(id);if(!vehicle)return;try{const{data}=await window.axios.get('/admin/gps-devices/fleet',{params:{search:vehicle.registration_number}});if(version!==locateRequest)return;const p=data.devices.find(p=>p.vehicle_id===id&&p.latitude!==null&&p.longitude!==null);if(p)map.setView([p.latitude,p.longitude],16);}catch{}};
    $('vf-vehicle-search').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{vehiclePage=1;vehicles();},300);};
    for(const[name,action]of[['vehicles',vehicles],['zones',zones],['history',history]])for(const[dir,delta]of[['prev',-1],['next',1]])$('vf-'+name+'-'+dir).onclick=()=>{if(name==='vehicles')vehiclePage+=delta;if(name==='zones')zonePage+=delta;if(name==='history')historyPage+=delta;action();};
    const channel=window.Echo?.private('admin.gps.alerts').listen('.vehicle.geofence.alert',()=>history());
    window.addEventListener('pagehide',()=>{window.Echo?.leave('admin.gps.alerts');drawing?.disable();clearTimeout(searchTimer);map.remove();},{once:true});
    vehicles();zones();history();
}
