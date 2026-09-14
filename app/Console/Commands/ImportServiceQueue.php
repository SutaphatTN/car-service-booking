<?php

namespace App\Console\Commands;

use App\Support\ServiceQueueImporter;
use Illuminate\Console\Command;

/**
 * ดึงรถที่ส่งมอบแล้วจากฐานข้อมูลฝ่ายขาย มาสร้างคิวเช็คระยะครั้งถัดไป
 * รันซ้ำได้ไม่เกิดข้อมูลซ้ำ และไม่ทับแถวที่เจ้าหน้าที่แก้ไปแล้ว
 */
class ImportServiceQueue extends Command
{
    protected $signature = 'service:import
        {--brand=* : จำกัดยี่ห้อ เช่น --brand=GWM (ไม่ใส่ = ทุกยี่ห้อที่เปิดใช้งาน)}';

    protected $description = 'ดึงรถที่ส่งมอบแล้วมาสร้างคิวเช็คระยะ';

    public function handle(ServiceQueueImporter $importer): int
    {
        $brands = $this->option('brand') ?: null;

        $this->info('กำลังดึงข้อมูลจากฐานข้อมูลฝ่ายขาย...');

        $result = $importer->import($brands);

        $this->newLine();
        $this->line('  สร้างคิวใหม่        '.$result['created'].' คัน');
        $this->line('  อัปเดตตามต้นทาง     '.$result['updated'].' แถว');
        $this->line('  ลบ (ต้นทางหายไป)    '.$result['deleted'].' แถว');

        if ($result['flagged']) {
            $this->newLine();
            $this->warn('  ขึ้นธงเตือน '.$result['flagged'].' แถว — ต้นทางหายไปแล้วแต่นัด/เช็คไปแล้ว จึงไม่ลบให้');
            $this->line('  ดูได้ที่หน้า "ต้องโทรหา" และหน้ารายการนัดหมาย');
        }

        if ($result['unsupported']) {
            $this->newLine();
            $this->warn('รุ่นที่ยังไม่มีตารางเช็คระยะ — เพิ่มได้ที่ app/Support/ServiceSchedule.php');
            foreach ($result['unsupported'] as $model) {
                $this->line('  - '.$model);
            }
        }

        return self::SUCCESS;
    }
}
