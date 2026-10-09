{{--
    ไอคอนของปุ่มในตาราง — ประกาศทีเดียวต่อหน้า แล้วแต่ละแถวอ้างด้วย <use href="#ic-xxx">
    ไม่ต้องซ้ำ path เดิมทุกแถว (ยี่สิบแถว × สามปุ่ม = หกสิบชุด)
--}}
<svg width="0" height="0" aria-hidden="true" style="position:absolute">
    {{-- หน้าต้องโทรหา --}}
    <symbol id="ic-booking" viewBox="0 0 24 24">
        <rect x="3.5" y="4.5" width="17" height="16" rx="2.5"/>
        <path d="M8 2.5v4M16 2.5v4M3.5 9.5h17M9 14.5l2 2 4-4"/>
    </symbol>
    <symbol id="ic-postpone" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="8.5"/>
        <path d="M12 7v5l3.5 2"/>
    </symbol>
    <symbol id="ic-decline" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="8.5"/>
        <path d="M6.2 17.8 17.8 6.2"/>
    </symbol>

    {{-- หน้ารายการนัดหมาย --}}
    <symbol id="ic-complete" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="8.5"/>
        <path d="m8.3 12.3 2.5 2.5 4.9-5.4"/>
    </symbol>
    <symbol id="ic-restore" viewBox="0 0 24 24">
        <path d="M3.5 12a8.5 8.5 0 1 0 2.5-6L3.5 8.5"/>
        <path d="M3.5 3.8v4.7h4.7"/>
    </symbol>
    <symbol id="ic-edit" viewBox="0 0 24 24">
        <path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z"/>
        <path d="m13.5 8.5 2 2"/>
    </symbol>
    <symbol id="ic-trash" viewBox="0 0 24 24">
        <path d="M4 6.5h16M9.5 6.5v-2h5v2M17.8 6.5l-.9 13H7.1l-.9-13M10 10.5v5.5M14 10.5v5.5"/>
    </symbol>
</svg>
