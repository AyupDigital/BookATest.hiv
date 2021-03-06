<?php

namespace App\Docs\Resources;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class EligibleAnswerResource extends BaseResource
{
    /**
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Schema
     */
    public static function resource(): Schema
    {
        return Schema::object()->properties(
            Schema::string('id')->format(Schema::FORMAT_UUID),
            Schema::string('question_id')->format(Schema::FORMAT_UUID),
            Schema::string('question_id')->format(Schema::FORMAT_UUID),
            Schema::object('answer')->properties(
                Schema::string('type'),
                Schema::integer('interval')
            ),
            Schema::string('created_at')->format('date-time'),
            Schema::string('updated_at')->format('date-time')
        );
    }
}
