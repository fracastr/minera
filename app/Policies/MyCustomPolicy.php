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
            'unsafe-inline',
            'sha256-hash',

        ]);

        //$this->addDirective(Directive::SCRIPT, Keyword::SELF); // will output `'self'` when outputting headers
        //$this->addDirective(Directive::STYLE, 'sha256-hash'); // will output `'sha256-hash'` when outputting headers

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
            Keyword::SELF,
            Keyword::UNSAFE_INLINE,


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
