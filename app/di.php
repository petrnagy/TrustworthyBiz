<?php

class DynamicMockupDI {
    public function __call($closure, $args) {
        return call_user_func_array($this->{$closure}->bindTo($this), $args);
    } // end method
} // end class
