{{--
    ช่องรถในตาราง — รุ่นหลัก / รุ่นย่อย / เลขตัวถัง ใช้รูปแบบเดียวกันทุกหน้า
    นัดที่คีย์เองไม่มีรุ่น (มีแต่ VIN) ก็แสดงแค่ VIN
--}}
@if ($appointment->model_name)
    <b class="model-name">{{ $appointment->model_name }}</b>
@endif
@if ($appointment->submodel_name)
    <div class="sub submodel-name">{{ $appointment->submodel_name }}</div>
@endif
@if ($appointment->vin)
    <div class="sub"><code class="vin">{{ $appointment->vin }}</code></div>
@elseif (! $appointment->model_name)
    <span class="sub">-</span>
@endif
