<?php
/**
 *           Doorkeeper Trait
 *
 *              Prevent setting new properties on classes
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\class_traits;

trait no_set {
    public function __set($name, $value) {
      throw new \Exception("Adding new properties is not allowed on " . __CLASS__);
    }
}