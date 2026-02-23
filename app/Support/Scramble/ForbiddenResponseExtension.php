<?php

namespace App\Support\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\RouteInfo;

class ForbiddenResponseExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        // Kita buat schema-nya dulu agar lebih rapi
        $forbiddenSchema = Schema::fromType(
            (new ObjectType())
                ->addProperty('success', (new BooleanType())->default(false))
                ->addProperty('message', (new StringType())->default('Anda tidak memiliki hak akses untuk melakukan tindakan ini.'))
        );

        // Menambahkan response 403 dengan cara yang lebih modern
        $operation->addResponse(
            Response::make(403)
                ->setContent('application/json', $forbiddenSchema)
        );
    }
}