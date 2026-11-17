<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Application extends Model
{
    protected $table = 'applications';

    /**
     * Контроллер сам формирует белый список сохраняемых полей.
     */
    protected $guarded = ['id'];

    protected $casts = [
        'id_insuranceCompany' => 'integer',
        'id_status' => 'integer',
        'id_MRK' => 'integer',
        'id_spm' => 'integer',
        'id_mentor' => 'integer',
        'id_lawyer' => 'integer',
        'our_company' => 'integer',
        'id_city' => 'integer',
        'id_service' => 'integer',
        'mileage' => 'integer',

        // В базе: tinyint(1). 1 — мужской, 0 — женский.
        'gender' => 'integer',
        'ub_type' => 'integer',
        'ic_answer' => 'integer',
        'nds' => 'boolean',

        'DTPdate' => 'date:Y-m-d',
        'ClientPasport_date' => 'date:Y-m-d',
        'birthday' => 'date:Y-m-d',
        'napr_date' => 'date:Y-m-d',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'sp_date' => 'date:Y-m-d',
        'ic_pay_date' => 'date:Y-m-d',
        'sto_pay_date' => 'date:Y-m-d',
        'zp_date' => 'date:Y-m-d',
        'finish_pay_date' => 'date:Y-m-d',

        'view_date' => 'datetime:Y-m-d H:i:s',
        'check_date' => 'datetime:Y-m-d H:i:s',
        'ic_answer_date' => 'datetime:Y-m-d H:i:s',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'id_status');
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'id_insuranceCompany');
    }

    public function ourCompany(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class, 'our_company');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'id_city');
    }

    public function mrk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_MRK');
    }

    public function spm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_spm');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_mentor');
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class, 'id_lawyer');
    }
}