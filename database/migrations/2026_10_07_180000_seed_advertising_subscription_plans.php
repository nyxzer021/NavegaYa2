<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('subscription_plans')->upsert([
            ['code'=>'basic','name'=>'Básico','description'=>'Visibilidad inicial para negocios locales.','monthly_price'=>300,'currency'=>'PEN','benefits'=>json_encode(['ad_limit'=>1,'impressions'=>5000,'placements'=>1,'features'=>['1 anuncio simultáneo','5,000 impresiones/mes','1 placement (homepage)','Analytics básico']]),'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'professional','name'=>'Profesional','description'=>'Cobertura ampliada para empresas en crecimiento.','monthly_price'=>750,'currency'=>'PEN','benefits'=>json_encode(['ad_limit'=>3,'impressions'=>15000,'placements'=>3,'features'=>['3 anuncios simultáneos','15,000 impresiones/mes','3 placements','Analytics avanzado + A/B testing']]),'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'enterprise','name'=>'Enterprise','description'=>'Cobertura completa y acompañamiento personalizado.','monthly_price'=>1500,'currency'=>'PEN','benefits'=>json_encode(['ad_limit'=>null,'impressions'=>50000,'placements'=>99,'features'=>['Anuncios ilimitados','50,000 impresiones/mes','Todos los placements','Account Manager 24/7']]),'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
        ], ['code'], ['name','description','monthly_price','currency','benefits','is_active','updated_at']);
    }

    public function down(): void
    {
        // Planes comerciales conservados para no romper contratos históricos.
    }
};
