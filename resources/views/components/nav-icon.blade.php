@props(['name'])
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
@switch($name)
@case('boat')<path d="M3 17h18l-2 3H5l-2-3Z"/><path d="M5 17 7 7h10l2 10M12 3v4M8 11h8"/>@break
@case('plane')<path d="M10.2 20 12 22l1.8-2 1-6 5.2-3.1a2 2 0 0 0-2-3.4L13 9l-1-6H9l-.3 6-4 2.4L3 10l-1.5 1.5 3.3 3.3L9 14l1.2 6Z"/>@break
@case('building')<path d="M4 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17M2 21h20M8 7h4M8 11h4M8 15h4M17 9h2M17 13h2"/>@break
@case('handshake')<path d="m7 11 3 3a2 2 0 0 0 3 0l4-4M3 9l3-3 4 3M21 9l-3-3-4 3M6 14l2 2M9 17l2 2M15 15l-2 2"/>@break
@case('pin')<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>@break
@case('route')<circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M8 18c5 0 1-8 8-8M16 10h4V6"/>@break
@case('seats')<path d="M5 11V5a2 2 0 0 1 4 0v6M5 11h10a3 3 0 0 1 3 3v3H5v-6ZM3 21h18M7 17v4M17 17v4"/>@break
@case('ticket')<path d="M3 8a2 2 0 0 0 2-2V4h14v2a2 2 0 0 0 2 2v8a2 2 0 0 0-2 2v2H5v-2a2 2 0 0 0-2-2V8Z"/><path d="M13 4v16"/>@break
@case('payment')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>@break
@case('chart')<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>@break
@case('leaf')<path d="M20 4C10 4 4 9 4 16c0 2 1 4 3 4 7 0 12-6 13-16Z"/><path d="M4 20c3-5 7-8 12-11"/>@break
@case('megaphone')<path d="M3 12v3a2 2 0 0 0 2 2h2l2 4h3l-1-4h2l6 3V6l-6 3H5a2 2 0 0 0-2 2v1Z"/>@break
@case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>@break
@case('shield')<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>@break
@case('settings')<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.04 1.55v.09h-3v-.09a1.7 1.7 0 0 0-1.04-1.55 1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7 15a1.7 1.7 0 0 0-1.55-1.04h-.09v-3h.09A1.7 1.7 0 0 0 7 9.92a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.12-2.12.06.06A1.7 1.7 0 0 0 10.66 6.26a1.7 1.7 0 0 0 1.04-1.55v-.09h3v.09a1.7 1.7 0 0 0 1.04 1.55 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.12 2.12-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.55 1.04h.09v3h-.09A1.7 1.7 0 0 0 19.4 15Z"/>@break
@case('user')<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>@break
@default<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
@endswitch
</svg>

