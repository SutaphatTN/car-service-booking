<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Brand;
use App\Models\Title;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * เทียบคิวเช็คระยะของเราให้ตรงกับฐานข้อมูลฝ่ายขาย
 *
 * ไม่ใช่แค่ "เพิ่มของใหม่" แต่เทียบให้ตรงทุกรอบ เพราะข้อมูลต้นทางแก้/ลบได้ตลอด
 *   เพิ่ม   รถที่ส่งมอบแล้วและยังไม่มีคิว
 *   อัปเดต ชื่อ เบอร์ VIN รุ่น วันส่งมอบ ให้ตรงต้นทางเสมอ
 *   ลบ     คิวที่ต้นทางหายไปแล้วและเจ้าหน้าที่ยังไม่ได้ทำอะไรกับมัน
 *   ขึ้นธง คิวที่ต้นทางหายแต่นัด/เช็คไปแล้ว — ลบไม่ได้เพราะจะทำให้ประวัติจริงหาย
 *
 * สิ่งที่ไม่แตะเลยคือของที่เจ้าหน้าที่เป็นคนกรอก — วันเวลาที่นัด สถานะ และเลขไมล์
 */
class ServiceQueueImporter
{
    /** เวลานัดตั้งต้นก่อนโทรยืนยันกับลูกค้า */
    public const DEFAULT_TIME = '08:00';

    /** รถส่งมอบใหม่ เลขไมล์เริ่มที่ 0 จนกว่ารถจะเข้าศูนย์จริง */
    private const INITIAL_MILEAGE = 0;

    /**
     * @return array{created:int, updated:int, deleted:int, flagged:int, unsupported:list<string>}
     */
    public function import(?array $brandNames = null): array
    {
        $brands = Brand::active()->get()->keyBy(fn (Brand $b) => mb_strtolower($b->name));
        $names = $brandNames ?? $brands->pluck('name')->all();
        $titles = Title::all()->keyBy(fn (Title $t) => $this->titleKey($t->name));

        $source = DeliveredCars::all($names)->keyBy('salecar_id');
        $ours = $this->existingQueues($brands, $names);

        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'flagged' => 0];
        $unsupported = [];

        foreach ($source as $salecarId => $car) {
            $brand = $brands->get(mb_strtolower((string) $car->brand));

            if (! $brand || ! ServiceSchedule::supports($car->brand, $car->model_id)) {
                $unsupported[] = $car->brand.' / '.($car->model ?? 'ไม่ระบุรุ่น');

                continue;
            }

            $existing = $ours->get($salecarId);

            if ($existing === null) {
                if ($this->create($car, $brand, $titles)) {
                    $stats['created']++;
                }

                continue;
            }

            $stats['updated'] += $this->refresh($existing, $car, $titles);
        }

        // คิวที่ต้นทางไม่มีแล้ว — ถูกลบ, ถอนสถานะส่งมอบ หรือวันส่งมอบหายไป
        foreach ($ours as $salecarId => $rows) {
            if ($source->has($salecarId)) {
                continue;
            }

            foreach ($rows as $row) {
                if ($row->confirmed_at === null && $row->completed_at === null) {
                    $row->delete();
                    $stats['deleted']++;

                    continue;
                }

                if ($row->source_missing_at === null) {
                    $row->forceFill(['source_missing_at' => now()])->save();
                    $stats['flagged']++;
                }
            }
        }

        return $stats + ['unsupported' => array_values(array_unique($unsupported))];
    }

    /**
     * คิวที่มีอยู่แล้ว จัดกลุ่มตาม salecar_id — รถคันหนึ่งมีได้หลายครั้ง
     *
     * จำกัดเฉพาะยี่ห้อที่กำลังดึงรอบนี้ ไม่งั้นสั่ง --brand=GWM แล้วคิวของ Wuling
     * จะดูเหมือนต้นทางหายไปทั้งหมดแล้วโดนลบทิ้ง
     *
     * @return Collection<int, EloquentCollection<int, Appointment>>
     */
    private function existingQueues(Collection $brands, array $names): Collection
    {
        $brandIds = collect($names)
            ->map(fn (string $name) => $brands->get(mb_strtolower($name))?->id)
            ->filter()
            ->all();

        return Appointment::withoutGlobalScope('brand')
            ->whereNotNull('salecar_id')
            ->whereIn('brand_id', $brandIds)
            ->get()
            ->groupBy('salecar_id');
    }

    /** สร้างคิวครั้งแรกของรถคันนี้ */
    private function create(object $car, Brand $brand, Collection $titles): bool
    {
        $service = ServiceSchedule::serviceFor(
            $car->brand, $car->model_id, Carbon::parse($car->delivered_at), 1
        );

        if ($service === null) {
            return false;
        }

        Appointment::withoutGlobalScope('brand')->create(
            $this->sourceFields($car, $titles) + [
                'brand_id' => $brand->id,
                'mileage' => self::INITIAL_MILEAGE,
                'appointment_at' => $this->dueAt($service['due_date']),
                'salecar_id' => $car->salecar_id,
                'service_no' => $service['service_no'],
            ]
        );

        return true;
    }

    /**
     * อัปเดตข้อมูลที่ฝ่ายขายเป็นเจ้าของให้ตรงต้นทาง
     * วันครบกำหนดคำนวณใหม่เฉพาะคิวที่ยังไม่ได้โทรยืนยัน — ถ้าโทรนัดกับลูกค้าแล้วห้ามขยับ
     *
     * @param  EloquentCollection<int, Appointment>  $rows
     * @return int จำนวนแถวที่มีการเปลี่ยนแปลงจริง
     */
    private function refresh(EloquentCollection $rows, object $car, Collection $titles): int
    {
        $changed = 0;

        foreach ($rows as $row) {
            $fields = $this->sourceFields($car, $titles);

            // ต้นทางกลับมาแล้ว ปลดธงเตือนออก
            if ($row->source_missing_at !== null) {
                $fields['source_missing_at'] = null;
            }

            if ($row->needsCall()) {
                $service = ServiceSchedule::serviceFor(
                    $car->brand, $car->model_id, Carbon::parse($car->delivered_at), (int) $row->service_no
                );

                if ($service !== null) {
                    $fields['appointment_at'] = $this->dueAt($service['due_date']);
                }
            }

            $row->forceFill($fields);

            if ($row->isDirty()) {
                $row->save();
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * สร้างคิวครั้งถัดไปหลังปิดงานครั้งนี้ — คืน null เมื่อจบตารางแล้ว
     * นับต่อจากวันส่งมอบเดิมเสมอ ไม่ใช่นับจากวันที่เพิ่งเช็คเสร็จ
     */
    public function createNextFor(Appointment $done): ?Appointment
    {
        if ($done->salecar_id === null || $done->delivered_at === null) {
            return null;
        }

        $next = ServiceSchedule::serviceFor(
            $done->brand?->name ?? '', $done->model_id, $done->delivered_at->copy(), $done->service_no + 1
        );

        if ($next === null) {
            return null;
        }

        $already = Appointment::withoutGlobalScope('brand')
            ->where('salecar_id', $done->salecar_id)
            ->where('service_no', $next['service_no'])
            ->first();

        if ($already) {
            return $already;
        }

        return Appointment::withoutGlobalScope('brand')->create([
            'brand_id' => $done->brand_id,
            'title_id' => $done->title_id,
            'first_name' => $done->first_name,
            'last_name' => $done->last_name,
            'phone' => $done->phone,
            'phone2' => $done->phone2,
            'vin' => $done->vin,
            'delivered_at' => $done->delivered_at,
            'mileage' => self::INITIAL_MILEAGE,
            'appointment_at' => $this->dueAt($next['due_date']),
            'salecar_id' => $done->salecar_id,
            'service_no' => $next['service_no'],
            'model_id' => $done->model_id,
            'model_name' => $done->model_name,
        ]);
    }

    /**
     * ช่องที่ฝ่ายขายเป็นเจ้าของ — ทับของเราได้เสมอ
     * เก็บตามที่เขากรอกมา ไม่เดาเติมให้ เช่นเบอร์ที่ขาด 0 นำหน้า
     *
     * @return array<string, mixed>
     */
    private function sourceFields(object $car, Collection $titles): array
    {
        return [
            'title_id' => $titles->get($this->titleKey($car->title))?->id,
            'first_name' => trim((string) $car->first_name),
            'last_name' => trim((string) $car->last_name),
            'phone' => DeliveredCars::normalisePhone($car->phone),
            'phone2' => DeliveredCars::normalisePhone($car->phone2),
            'vin' => DeliveredCars::normaliseVin($car->vin),
            'delivered_at' => $car->delivered_at,
            'model_id' => $car->model_id,
            'model_name' => $car->model,
        ];
    }

    /** วันครบกำหนด + เวลาตั้งต้น (เจ้าหน้าที่แก้ตอนโทรยืนยัน) */
    private function dueAt(Carbon $dueDate): Carbon
    {
        [$hour, $minute] = explode(':', self::DEFAULT_TIME);

        return $dueDate->copy()->setTime((int) $hour, (int) $minute);
    }

    /** จับคู่คำนำหน้าด้วยชื่อ ตัดช่องว่างและจุดออกกันคลาดเคลื่อน เช่น "Mr." กับ "Mr" */
    private function titleKey(?string $name): string
    {
        return mb_strtolower(str_replace([' ', '.', "\u{00A0}"], '', (string) $name));
    }
}
