<?php

namespace App\Http\Controllers;


    /**
     * @OA\OpenApi(
     *     @OA\Info(
     *         title="DMS PDU API",
     *         version="1.0.0",
     *         description="API documentation for DMS PDU project"
     *     ),
     *     @OA\Tag(name="Authentication", description="Auth endpoints"),
     *     @OA\Tag(name="Verification", description="Auth verification endpoints"),
     *     @OA\Tag(name="Users", description="User management"),
     *     @OA\Tag(name="Password", description="Password endpoints"),
     * )
     *
     * @OA\Server(
     *     url="http://127.0.0.1:8000",
     *     description="Local server"
     * )
     */
abstract class Controller
{
    //
}
