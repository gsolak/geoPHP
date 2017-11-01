<?php

namespace Phayes\GeoPHP\Adapters;

use Exception;
use Phayes\GeoPHP\Geometry\Geometry;
use Phayes\GeoPHP\Geometry\GeometryCollection;
use Phayes\GeoPHP\Geometry\LineString;
use Phayes\GeoPHP\Geometry\MultiLineString;
use Phayes\GeoPHP\Geometry\MultiPoint;
use Phayes\GeoPHP\Geometry\MultiPolygon;
use Phayes\GeoPHP\Geometry\Point;
use Phayes\GeoPHP\Geometry\Polygon;
use Phayes\GeoPHP\GeoPHP;

class GeoJSON extends GeoAdapter
{
  /**
   * Given an object or a string, return a Geometry
   * @param mixed $input The GeoJSON string or object
   * @return object Geometry
   *
   * @throws Exception
   */
  public function read($input)
  {
    if (is_string($input)) {
      $input = json_decode($input);
    }

    if (!is_object($input)) {
      throw new Exception('Invalid GeoJSON');
    }

    if (!is_string($input->type)) {
      throw new Exception('Invalid GeoJSON');
    }

    // Check to see if it's a FeatureCollection
    if ($input->type == 'FeatureCollection') {
      $geoms = array();
      foreach ($input->features as $feature) {
        $geoms[] = $this->read($feature);
      }
      return GeoPHP::geometryReduce($geoms);
    }

    // Check to see if it's a Feature
    if ($input->type === 'Feature') {
      return $this->read($input->geometry);
    }

    // It's a geometry - process it
    return $this->objToGeom($input);
  }

  /**
   * Obj to geom -
   * @param \stdClass $obj
   * @return GeometryCollection
   */
  private function objToGeom($obj)
  {
    $type = $obj->type;
    if ($type == 'GeometryCollection') {
      return $this->objToGeometryCollection($obj);
    }
    $method = 'arrayTo' . $type;
    return $this->$method($obj->coordinates);
  }

  /**
   * Array to point -
   * @param array $array
   * @return Point
   */
  private function arrayToPoint(array $array)
  {
    return (!empty($array)) ? new Point($array[0], $array[1]) : new Point();
  }

  /**
   * Array to line string -
   * @param array $array
   * @return LineString
   */
  private function arrayToLineString(array $array)
  {
    $points = [];
    foreach ($array as $comp_array) {
      $points[] = $this->arrayToPoint($comp_array);
    }
    return new LineString($points);
  }

  /**
   * Array to polygon -
   * @param array $array
   * @return Polygon
   */
  private function arrayToPolygon(array $array)
  {
    $lines = [];
    foreach ($array as $comp_array) {
      $lines[] = $this->arrayToLineString($comp_array);
    }
    return new Polygon($lines);
  }

  /**
   * Array to multi point -
   * @param array $array
   * @return MultiPoint
   */
  private function arrayToMultiPoint(array $array)
  {
    $points = [];
    foreach ($array as $comp_array) {
      $points[] = $this->arrayToPoint($comp_array);
    }
    return new MultiPoint($points);
  }

  /**
   * array to multiline stirng -
   * @param array $array
   * @return MultiLineString
   */
  private function arrayToMultiLineString(array $array)
  {
    $lines = [];
    foreach ($array as $comp_array) {
      $lines[] = $this->arrayToLineString($comp_array);
    }
    return new MultiLineString($lines);
  }

  /**
   * Array to multi polygon -
   * @param array $array
   * @return MultiPolygon
   */
  private function arrayToMultiPolygon(array $array)
  {
    $polygons = [];
    foreach ($array as $comp_array) {
      $polygons[] = $this->arrayToPolygon($comp_array);
    }
    return new MultiPolygon($polygons);
  }

  /**
   * Obj to geometry collection -
   * @param \stdClass $obj
   * @return GeometryCollection
   * @throws Exception
   */
  private function objToGeometryCollection($obj)
  {
    $geometries = [];
    if (empty($obj->geometries)) {
      throw new Exception('Invalid GeoJSON: GeometryCollection with no component geometries');
    }
    foreach ($obj->geometries as $comp_object) {
      $geometries[] = $this->objToGeom($comp_object);
    }
    return new GeometryCollection($geometries);
  }

  /**
   * Serializes an object into a geojson string
   * @param Geometry $geometry The object to serialize
   * @param bool $returnArray
   * @return array|string The GeoJSON string
   */
  public function write(Geometry $geometry, $returnArray = false)
  {
    return ($returnArray) ? $this->getArray($geometry) : json_encode($this->getArray($geometry));
  }

  /**
   * Get array -
   * @param Geometry $geometry
   * @return array
   */
  public function getArray($geometry)
  {
    if ($geometry->getGeomType() === 'GeometryCollection') {
      $component_array = [];
      /** @var Geometry $component */
      foreach ($geometry->components as $component) {
        $component_array[] = [
          'type' => $component->geometryType(),
          'coordinates' => $component->asArray(),
        ];
      }
      return [
        'type'=> 'GeometryCollection',
        'geometries'=> $component_array,
      ];
    }
    return [
      'type'=> $geometry->getGeomType(),
      'coordinates'=> $geometry->asArray(),
    ];
  }
}
