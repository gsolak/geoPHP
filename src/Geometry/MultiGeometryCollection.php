<?php

namespace Phayes\GeoPHP\Geometry;

/**
 * MultiPolygon: A collection of Polygons
 */
class MultiGeometryCollection extends Collection
{
  protected $geom_type = 'MultiGeometry';
}
