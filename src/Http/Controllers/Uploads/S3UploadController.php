<?php

namespace App\Http\Controllers\Uploads;

use App\Http\Controllers\Controller;
use App\JsonApi\Proxies\Models\PublicFile;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File as RulesFile;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Core\Responses\DataResponse;
use Throwable;

class S3UploadController extends Controller
{

    public function s3_store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files.*.attachment' => [
                'required',
                RulesFile::types(['mp3', 'wav', 'png', 'jpg', 'jpeg', 'mp4'])
                    ->max(12 * 1024),
            ],
            'files.*.object_position' => [
                'required',
                'string',

            ]
        ]);

        // $validator = Validator::make($request->all(), [
        //     'attachment' => [
        //         'required',
        //         RulesFile::types(['mp3', 'wav', 'png', 'jpg', 'jpeg', 'mp4'])
        //             ->max(12 * 1024),
        //     ],
        // ]);

        if ($validator->fails()) {
            $m = $validator->getMessageBag()->messages();

            $errors = new ErrorList();

            foreach ($m as $source_pointer => $error) {
                foreach ($error as $detail) {
                    $errors->push(Error::fromArray([
                        'detail' => $detail,
                        'source' => [
                            'pointer' => $source_pointer,
                        ],
                        'status' => 422,
                        "title" => "Unprocessable Entity"
                    ]));
                }
            }

            return $errors->prepareResponse($request);
        }

        $validated = $validator->validated();

        $file = $validated['attachment'];

        try {
            $path = File::storage_put($file);

            $model = new File([
                "client_original_name" => $file->getClientOriginalName(),
                "path" => $path,
            ]);

            $model->user()->associate($request->user('api'));
            $model->save();
        } catch (Throwable $e) {
            return Error::fromArray([
                'detail' => $e,
                'source' => [
                    'pointer' => 'File Model Error',
                ],
                'status' => 500,
                "title" => "Internal Server Error"
            ]);
        }

        return DataResponse::make(PublicFile::wrap($model))
            ->withServer('v1.public')
            ->didCreate();
    }
}
