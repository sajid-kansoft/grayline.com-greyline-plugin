<?php
/**
 * Created by PhpStorm.
 * User: Iona Fortune iona@senshi.digital iona.fortune@icloud.com
 * Date: 22/03/2017
 * Time: 13:57
 */


class LicenseeFactory
{

    public static function create($row)
    {
        loadSenshiModal("licensee");
        
        return new Licensee($row);
    }


}