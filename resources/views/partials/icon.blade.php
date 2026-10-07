{{-- Simple line icons, drawn for Tribe. --}}
<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
@switch($name)
    @case('home')<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/><path d="M10 19.5v-5h4v5"/>@break
    @case('calendar')<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>@break
    @case('list')<path d="M9 6.5h11M9 12h11M9 17.5h11"/><path d="m3.5 6.5 1.3 1.3L7 5.5M3.5 12l1.3 1.3L7 11M3.5 17.5l1.3 1.3L7 16.5"/>@break
    @case('heart')<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10Z"/>@break
    @case('more')<circle cx="5.5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="18.5" cy="12" r="1.3"/>@break
    @case('plus')<path d="M12 5v14M5 12h14"/>@break
    @case('back')<path d="m14.5 5-7 7 7 7"/>@break
    @case('next')<path d="m9.5 5 7 7-7 7"/>@break
    @case('phone')<path d="M5 4h3.5l1.5 4-2 1.5a11 11 0 0 0 6.5 6.5l1.5-2 4 1.5V19a1.5 1.5 0 0 1-1.5 1.5C10.5 20.5 3.5 13.5 3.5 5.5A1.5 1.5 0 0 1 5 4Z"/>@break
    @case('pin')<path d="M12 21s6.5-6.1 6.5-11.2a6.5 6.5 0 0 0-13 0C5.5 14.9 12 21 12 21Z"/><circle cx="12" cy="9.8" r="2.3"/>@break
    @case('cake')<path d="M4 20.5h16M5 20.5v-7h14v7"/><path d="M5 16.5c1.7 1.3 3.3 1.3 5 0 1.7 1.3 3.3 1.3 5 0 1.7 1.3 2.7 1.3 4 0"/><path d="M12 13.5v-3M12 7.5c-.9-.9-.9-2 0-3 .9 1 .9 2.1 0 3Z"/>@break
    @case('people')<circle cx="9" cy="8.5" r="3"/><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0"/><path d="M15.5 5.8a3 3 0 0 1 0 5.4M17.5 14.5a5.5 5.5 0 0 1 3 5"/>@break
    @case('book')<path d="M5 4.5h11.5a1.5 1.5 0 0 1 1.5 1.5v14H6.5A1.5 1.5 0 0 1 5 18.5v-14Z"/><path d="M5 18.5A1.5 1.5 0 0 1 6.5 17H18"/>@break
    @case('gear')<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>@break
    @case('door')<path d="M14 4.5H6.5v15H14"/><path d="M10.5 12H20M17 9l3 3-3 3"/>@break
    @case('check')<path d="m5 12.5 4.5 4.5L19 7.5"/>@break
    @case('trash')<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13"/>@break
    @case('share')<path d="M12 15V3.5M8 7.5l4-4 4 4"/><path d="M6.5 11H5v9.5h14V11h-1.5"/>@break
@endswitch
</svg>
