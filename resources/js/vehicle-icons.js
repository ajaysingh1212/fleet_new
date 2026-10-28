const icons = new Set(['fa-car-side', 'fa-car', 'fa-taxi', 'fa-motorcycle', 'fa-bicycle', 'fa-van-shuttle', 'fa-bus', 'fa-truck', 'fa-truck-pickup', 'fa-trailer', 'fa-tractor', 'fa-truck-monster', 'fa-truck-medical', 'fa-truck-fast', 'fa-ship', 'fa-location-dot']);

export function vehicleIcon(position) {
    if (icons.has(position.vehicle_icon)) return position.vehicle_icon;
    if (['bike', 'motorcycle', 'motorbike'].includes(position.vehicle_type)) return 'fa-motorcycle';
    return 'fa-car-side';
}
