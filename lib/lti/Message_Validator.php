<?php
namespace IMSGlobal\LTI13;

interface Message_Validator {
    public function validate($jwt_body);
    public function can_validate($jwt_body);
}
?>