<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DiagnosticEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiagnosticEventRequest extends FormRequest
{
    /**
     * Browser clients can only report signals a browser tab can actually
     * observe about itself — not device-level events like phone restarts
     * or app kills, which belong to the mobile clients of later increments.
     */
    private const ALLOWED_TYPES = [
        DiagnosticEventType::BrowserHidden,
        DiagnosticEventType::BrowserVisible,
        DiagnosticEventType::BrowserClosed,
        DiagnosticEventType::BrowserRefreshed,
        DiagnosticEventType::PermissionGranted,
        DiagnosticEventType::PermissionRevoked,
        DiagnosticEventType::GpsEnabled,
        DiagnosticEventType::GpsDisabled,
        DiagnosticEventType::InternetOn,
        DiagnosticEventType::InternetOff,
        DiagnosticEventType::PoorAccuracy,
        DiagnosticEventType::AutomationDetected,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::enum(DiagnosticEventType::class)->only(self::ALLOWED_TYPES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'reason' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'network_type' => ['nullable', 'string', 'max:20'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            // Offline events can only be transmitted once the client is back online,
            // so the client-observed time (not the server receipt time) is authoritative.
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
