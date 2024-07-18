<?php
namespace App\Policies;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policies\Basic;

class MyCustomPolicy extends Basic
{
    public function configure()
    {
        parent::configure();

//        //Inline Styles,Script
        $this->addNonceForDirective(Directive::SCRIPT);
        $this->addNonceForDirective(Directive::STYLE);
    }
}
