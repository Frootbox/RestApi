<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 */

namespace FrootBox\RestApi\Attribute;

#[\Attribute]
class Auth {
    
    public function __construct(
        Client|Bearer $type,    
    )
    { }    
}
