<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\ChargeCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Write operations of the Charges master (charges inside a charge category and their per patient-category amounts).
 */
class ChargeCatalogueService
{
    /** Tables that reference a charge once it has been billed / added to an OPD, IPD or DCD service. */
    private const USAGE_TABLES = ['billing_charges_detail', 'ipd_service_detail', 'opd_service_detail', 'dcd_service_detail'];

    /**
     * Create a charge in a category (the department is taken from the category) with its amount per patient category.
     *
     * @param  int[]|string[]    $patientCategoryIds
     * @param  float[]|string[]  $amounts            same positions as $patientCategoryIds
     */
    public function createInCategory(ChargeCategory $category, string $name, string $code, array $patientCategoryIds, array $amounts): Charge
    {
        return DB::transaction(function () use ($category, $name, $code, $patientCategoryIds, $amounts) {
            $charge = Charge::create([
                'charge_name'          => $name,
                'charge_code'          => $code,
                'charge_department_id' => $category->charge_department_id,
                'charge_category_id'   => $category->id,
            ]);

            foreach (array_values($patientCategoryIds) as $i => $patientCategoryId) {
                DB::table('patient_categories_charges')->insert([
                    'patient_category_id' => (int) $patientCategoryId,
                    'charge_id'           => $charge->id,
                    'amount'              => (float) ($amounts[$i] ?? 0),
                ]);
            }

            return $charge;
        });
    }

    /** Insert or update the amount a patient category pays for a charge. */
    public function setAmount(int $chargeId, int $patientCategoryId, float $amount): void
    {
        DB::table('patient_categories_charges')->updateOrInsert(
            ['charge_id' => $chargeId, 'patient_category_id' => $patientCategoryId],
            ['amount' => $amount]
        );
    }

    /** True when a charge has been billed anywhere and therefore must not be deleted. */
    public function isInUse(int $chargeId): bool
    {
        foreach (self::USAGE_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('charge_id', $chargeId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** Delete a charge and its amounts unless it is in use. Returns false when it is in use. */
    public function deleteUnused(Charge $charge): bool
    {
        if ($this->isInUse($charge->id)) {
            return false;
        }

        DB::transaction(function () use ($charge) {
            if (Schema::hasTable('patient_categories_charges')) {
                DB::table('patient_categories_charges')->where('charge_id', $charge->id)->delete();
            }
            $charge->delete();
        });

        return true;
    }
}
