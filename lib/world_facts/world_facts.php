<?php
/**
 *    A class to show various facts and estimates about the world we are living in.
 * 
 *      Author: Jacob (JacobSeated)
 * 
 *      Usage:
 * 
 *       $earth = new world_facts();
 *       echo $earth->get_shape();
 *       echo $earth->population_by_year(2050);
 * 
 *      This class was used as an example in the Objects tutorial: https://beamtic.com/objects-in-php
 * 
 */

namespace doorkeeper\lib\world_facts;

use Exception;

class world_facts {

  private $population_growth_rate, $population_base, $shape, $base_year;

  public function __construct() {

    $this->shape = 'Somewhat round anyway...';
    // Note. The Population growth rate is not exponential, since it may vary from year to year
    // Therefor, this can only be used to calculate a rough estimate..
    $this->population_growth_rate = 1.0105; // https://ourworldindata.org/world-population-growth (2020)
    $this->population_base = 7594000000; // (2020 estimate)
    $this->base_year = 2020;
  }

  public function get_current_population() {
    return $this->population_base;
  }

  public function get_shape() {
    return $this->population_base;
  }

  public function population_by_year(int $year) {
    if ($year < $this->base_year) {
      throw new Exception('It is not possible to go backwards in time.');
    }
    $n = $year-$this->base_year;
    // PHP uses "**" for exponentation instead of caret "^"
    // See: https://beamtic.com/exponential-growth-php
    return $this->population_base*$this->population_growth_rate**$n;
  }

}



