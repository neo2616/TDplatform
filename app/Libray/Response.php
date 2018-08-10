<?php

namespace App\Libray;

class Response
{
    const ErrorType = 10900;
    const SuccessType = 200;
    const TokenErrorType = 2;

    static public function Success($Data = [])
    {
        $Res = [
            'code' => self::SuccessType,
            'msg'   => trans("ResponseMsg.Success"),
            'data'  => $Data
        ];

        return json_encode($Res);
    }


    static public function Error($Msg = '',$Data = [])
    {
        $Res = [
            'code' => self::ErrorType,
            'msg'   => $Msg?$Msg:trans("ResponseMsg.Error"),
            'data'  => $Data
        ];

        return json_encode($Res);
    }


    static public function TokenError($Msg = '',$Data = [])
    {
        $Res = [
            'code' => self::TokenErrorType,
            'msg'   => $Msg?$Msg:trans("ResponseMsg.Error"),
            'data'  => $Data
        ];

        return json_encode($Res);
    }
}