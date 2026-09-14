<?php

namespace App\Providers;

use App\Support\CurrentBrand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ตัวเดียวต่อหนึ่งคำขอ จะได้ไม่ต้องอ่าน session/ฐานข้อมูลซ้ำทุกครั้งที่ query
        $this->app->scoped(CurrentBrand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
