<?php

namespace App\Enums;

enum DiagnosticEventType: string
{
    case InternetOn = 'internet_on';
    case InternetOff = 'internet_off';
    case GpsEnabled = 'gps_enabled';
    case GpsDisabled = 'gps_disabled';
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';
    case BatterySaverOn = 'battery_saver_on';
    case BatterySaverOff = 'battery_saver_off';
    case BrowserClosed = 'browser_closed';
    case BrowserRefreshed = 'browser_refreshed';
    case BrowserHidden = 'browser_hidden';
    case BrowserVisible = 'browser_visible';
    case BrowserSleeping = 'browser_sleeping';
    case AppKilled = 'app_killed';
    case AppRestarted = 'app_restarted';
    case PhoneRestarted = 'phone_restarted';
    case MockGpsDetected = 'mock_gps_detected';
    case PoorAccuracy = 'poor_accuracy';
    case HighBatteryDrain = 'high_battery_drain';
    case WebSocketLost = 'websocket_lost';
    case WebSocketReconnected = 'websocket_reconnected';
    case ServerTimeout = 'server_timeout';
    case Overspeed = 'overspeed';
    case AutomationDetected = 'automation_detected';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
