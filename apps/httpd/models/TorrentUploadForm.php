<?php
/**
 * Created by PhpStorm.
 * User: Rhilip
 * Date: 2018/12/21
 * Time: 21:45
 */

namespace apps\httpd\models;

use Mix\Facades\Config;
use Mix\Validators\Validator;

class TorrentUploadForm extends Validator
{
    public $name;

    /**  @var \mix\Http\UploadFile */
    public $file;

    public $descr;

    public $uplver = "no";

    // 规则
    public function rules()
    {
        return [
            'name' => ['string', 'filter' => ['trim', 'strip_tags', 'htmlspecialchars']],
            'file' => ['torrent', 'mimes' => ["application/x-bittorrent"], 'maxSize' => Config::get("torrent.max_file_size")],
            'descr' => ['string', 'filter' => ['trim', 'strip_tags', 'htmlspecialchars']],
            'uplver' => ['in', 'range' => ['no', 'yes'], 'strict' => true],
        ];
    }

    // 场景
    public function scenarios()
    {
        return [
            'upload' => [
                'required' => ['name', 'file', 'descr'],
                'optional' => ['uplver']
            ],
        ];
    }
}