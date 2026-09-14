<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Support\DeliveredCars;
use App\Support\ServiceSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * ดูว่ารถที่ส่งมอบแล้วจะถูกคำนวณเป็นคิวเช็คระยะครั้งถัดไปเมื่อไหร่
 * อ่านอย่างเดียว ไม่เขียนอะไรลงฐานข้อมูลทั้งสิ้น
 */
class PreviewDeliveredCars extends Command
{
    protected $signature = 'service:preview
        {--brand=* : จำกัดยี่ห้อ เช่น --brand=GWM (ไม่ใส่ = ทุกยี่ห้อที่เปิดใช้งาน)}
        {--limit=20 : จำนวนแถวที่แสดง}
        {--problems : แสดงเฉพาะแถวที่ข้อมูลไม่ครบ}';

    protected $description = 'ดูรถที่ส่งมอบแล้วและวันครบกำหนดเช็คระยะครั้งถัดไป (ไม่เขียนฐานข้อมูล)';

    public function handle(): int
    {
        $brands = $this->option('brand') ?: Brand::active()->pluck('name')->all();

        $this->info('ยี่ห้อที่ดึง: '.implode(', ', $brands));

        $cars = DeliveredCars::all($brands);
        $this->line('พบรถที่ส่งมอบแล้ว '.$cars->count().' คัน');

        $rows = [];
        $problems = [];

        foreach ($cars as $car) {
            $phone = DeliveredCars::normalisePhone($car->phone);
            $vin = DeliveredCars::normaliseVin($car->vin);
            $delivered = Carbon::parse($car->delivered_at);

            $issues = [];
            if (! $vin) {
                $issues[] = 'ไม่มี VIN';
            }
            if (! $phone) {
                $issues[] = 'ไม่มีเบอร์';
            } elseif (! preg_match('/^0[0-9]{9}$/', $phone)) {
                $issues[] = 'เบอร์ผิดรูปแบบ ('.$phone.')';
            }
            if (! $car->title) {
                $issues[] = 'ไม่มีคำนำหน้า';
            }
            if (! ServiceSchedule::supports($car->brand, $car->model_id)) {
                $issues[] = 'ไม่มีตารางเช็คระยะของรุ่นนี้';
            }

            $next = ServiceSchedule::serviceFor($car->brand, $car->model_id, $delivered, 1);

            if ($issues) {
                $problems[] = [$car->salecar_id, $car->brand, mb_substr((string) $car->model, 0, 18),
                    trim($car->first_name.' '.$car->last_name), implode(' · ', $issues)];
            }

            $rows[] = [
                $car->salecar_id,
                $car->brand,
                mb_substr((string) $car->model, 0, 18),
                trim(($car->title ?? '').$car->first_name.' '.$car->last_name),
                $vin ?? '-',
                $delivered->toDateString(),
                $next ? $next['due_date']->toDateString() : '-',
                $next ? $next['months'].' ด.' : '-',
                $next && $next['mileage'] ? number_format($next['mileage']) : '-',
            ];
        }

        if ($this->option('problems')) {
            $this->newLine();
            $this->warn('แถวที่ข้อมูลไม่ครบ '.count($problems).' คัน');
            $this->table(['salecar', 'ยี่ห้อ', 'รุ่น', 'ลูกค้า', 'ปัญหา'], $problems);

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $this->newLine();
        $this->table(
            ['salecar', 'ยี่ห้อ', 'รุ่น', 'ลูกค้า', 'VIN', 'ส่งมอบ', 'ครบกำหนดครั้งที่ 1', 'ระยะ', 'กม.'],
            array_slice($rows, 0, $limit)
        );

        if (count($rows) > $limit) {
            $this->line('... แสดง '.$limit.' จาก '.count($rows).' คัน (ใช้ --limit เพื่อดูเพิ่ม)');
        }

        $this->newLine();
        $this->line('ข้อมูลไม่ครบ '.count($problems).' คัน — ดูด้วย --problems');

        return self::SUCCESS;
    }
}
