// Device IDs and vehicle IDs occupy different namespaces. A tracker without
// a vehicle must never overwrite another tracker under a shared null key.
export const fleetKey = p => p.device_id != null ? `device:${p.device_id}` : `vehicle:${p.vehicle_id}`;
export const fleetLabel = p => String(p.registration ?? '').trim() || String(p.imei ?? '').trim() || p.name || 'Unassigned vehicle';
export const streetDetailZoom = zoom => zoom >= 7;
