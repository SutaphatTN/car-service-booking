<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Title;
use App\Support\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    /** จำนวนรายการต่อหนึ่งหน้า */
    private const PER_PAGE = 10;

    /** ตารางนัดหมาย เรียงตามวันและเวลา แบ่งเป็นหน้า ๆ */
    public function index(): View
    {
        return view('appointments.index', [
            'appointments' => Appointment::with(['title', 'creator'])
                ->ordered()
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'titles' => Title::active()->get(),
            'timeSlots' => Schedule::timeSlots(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $appointment = Appointment::create([
            'title_id' => $request->validated('title_id'),
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'phone' => $request->validated('phone'),
            'mileage' => $request->validated('mileage'),
            'appointment_at' => $request->appointmentAt(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'บันทึกนัดหมายของ '.$appointment->load('title')->fullName().' แล้ว');
    }

    /** ลบได้เฉพาะ sa (กำหนดไว้ที่ route) */
    public function destroy(Appointment $appointment): RedirectResponse
    {
        $name = $appointment->load('title')->fullName();
        $appointment->delete();

        return back()->with('status', 'ลบนัดหมายของ '.$name.' แล้ว');
    }
}
