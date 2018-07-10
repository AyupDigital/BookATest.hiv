<?php

namespace App\Models;

use App\Models\Relationships\ReportScheduleRelationships;
use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    use ReportScheduleRelationships;

    const WEEKLY = 'weekly';
    const MONTHLY= 'monthly';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'clinic_id',
        'report_type_id',
        'repeat_type',
    ];
}