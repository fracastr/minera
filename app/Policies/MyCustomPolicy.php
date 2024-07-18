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


        //Styles
        $this->addDirective(Directive::STYLE, [
            'cdnjs.cloudflare.com',
            'fonts.googleapis.com',
            'cdn.datatables.net',
            'cdn.jsdelivr.net',
            'unsafe-inline'

        ]);

        //Fonts
        $this->addDirective(Directive::FONT, [
            'fonts.gstatic.com',
            'cdnjs.cloudflare.com',
            Keyword::SELF

        ]);

        //Script
        $this->addDirective(Directive::SCRIPT, [
            'cdn.datatables.net',
            'cdn.jsdelivr.net',
            'cdnjs.cloudflare.com',
            'connect.facebook.net',
            'www.google.com',
            'www.gstatic.com',


        ]);

        //Image
        $this->addDirective(Directive::IMG, [
            Keyword::SELF,
            Keyword::UNSAFE_INLINE,
            'data:'

        ]);

        //iFrame
        $this->addDirective(Directive::FRAME, [
            'go.crisp.chat',
            'www.google.com'
        ]);

//        //Inline Styles,Script
        $this->addNonceForDirective(Directive::SCRIPT);
        $this->addNonceForDirective(Directive::STYLE);
    }
}
