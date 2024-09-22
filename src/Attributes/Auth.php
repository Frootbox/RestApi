<?php
/**
 *
 */

namespace FrootBox\RestApi\Attributes;

#[\Attribute]
class Auth {
    
    public function __construct(
        Client|Bearer $type,    
    )
    { }    
}
