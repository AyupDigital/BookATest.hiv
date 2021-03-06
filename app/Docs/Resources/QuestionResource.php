<?php

namespace App\Docs\Resources;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class QuestionResource extends BaseResource
{
    /**
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Schema
     */
    public static function resource(): Schema
    {
        return Schema::object()->properties(
            Schema::string('id')->format(Schema::FORMAT_UUID),
            Schema::string('question'),
            Schema::string('type')->enum('select', 'checkbox', 'date', 'text'),
            Schema::array('options')->items(Schema::string())
        );
    }
}
