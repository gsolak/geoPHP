<?php

namespace Phayes\GeoPHP\Adapters;

use Phayes\GeoPHP\Geometry\Geometry;
use Phayes\GeoPHP\Geometry\GeometryCollection;

/**
 * GeoAdapter : abstract class which represents an adapter
 * for reading and writing to and from Geomtry objects
 */
abstract class GeoAdapter
{
  /**
   * Read input and return a Geomtry or GeometryCollection
   * @param string $input
   * @return GeometryCollection
   */
  abstract public function read($input);

  /**
   * Write out a Geometry or GeometryCollection in the adapter's format
   * @param Geometry $geometry
   * @return mixed
   */
  abstract public function write(Geometry $geometry);
}
