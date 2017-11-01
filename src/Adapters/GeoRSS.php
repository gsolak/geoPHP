<?php

namespace Phayes\GeoPHP\Adapters;

use Exception;
use Phayes\GeoPHP\Geometry\Geometry;
use Phayes\GeoPHP\Geometry\GeometryCollection;
use Phayes\GeoPHP\Geometry\LineString;
use Phayes\GeoPHP\Geometry\Point;
use Phayes\GeoPHP\Geometry\Polygon;
use Phayes\GeoPHP\GeoPHP;

class GeoRSS extends GeoAdapter
{
  /** @var bool  */
  private $namespace = false;

  /**
   * Namespace string. eg 'georss:'
   * @var string
   */
  private $nss = null;

  /**
   * Read GeoRSS string into geometry objects
   * @param string $gpx - an XML feed containing geoRSS
   * @return Geometry|GeometryCollection
   */
  public function read($gpx)
  {
    return $this->geomFromText($gpx);
  }

  /**
   * Serialize geometries into a GeoRSS string.
   * @param Geometry $geometry
   * @param bool $hasNamespace
   * @return string The georss string representation of the input geometries
   */
  public function write(Geometry $geometry, $hasNamespace = false)
  {
    if ($hasNamespace) {
      $this->namespace = $hasNamespace;
      $this->nss = $hasNamespace.':';
    }
    return $this->geometryToGeoRSS($geometry);
  }

  public function geomFromText($text)
  {
    // Change to lower-case, strip all CDATA, and de-namespace
    $text = strtolower($text);
    $text = preg_replace('/<!\[cdata\[(.*?)\]\]>/s','',$text);

    // Load into DOMDocument
    $xmlObj = new \DOMDocument();
    @$xmlObj->loadXML($text);
    if ($xmlObj === false) {
      throw new Exception("Invalid GeoRSS: ". $text);
    }
    $this->xmlobj = $xmlObj;
    try {
      $geom = $this->geomFromXML();
    }
//    catch(InvalidText $e) {
//        throw new Exception("Cannot Read Geometry From GeoRSS: ". $text);
//    }
    catch(Exception $e) {
        throw $e;
    }
    return $geom;
  }

  protected function geomFromXML()
  {
    $geometries = [];
    $geometries = array_merge($geometries, $this->parsePoints());
    $geometries = array_merge($geometries, $this->parseLines());
    $geometries = array_merge($geometries, $this->parsePolygons());
    $geometries = array_merge($geometries, $this->parseBoxes());
    $geometries = array_merge($geometries, $this->parseCircles());
    if (empty($geometries)) {
      throw new Exception("Invalid / Empty GeoRSS");
    }
    return geoPHP::geometryReduce($geometries);
  }

  protected function getPointsFromCoords($string)
  {
    $coordinates = [];
    if (empty($string)) {
      return $coordinates;
    }
    $latLong = explode(' ',$string);
    $lat = null;
    $long = null;
    foreach ($latLong as $key => $item) {
      if (!($key % 2)) {
        $lat = $item;
        continue;
      }
      $long = $item;
    }
    $coordinates[] = new Point($long, $lat);
    return $coordinates;
  }

  protected function parsePoints()
  {
    $points = [];
    $pt_elements = $this->xmlobj->getElementsByTagName('point');
    foreach ($pt_elements as $pt) {
      if ($pt->hasChildNodes()) {
        $point_array = $this->getPointsFromCoords(trim($pt->firstChild->nodeValue));
      }
      if (!empty($point_array)) {
        $points[] = $point_array[0];
        continue;
      }
      $points[] = new Point();
    }
    return $points;
  }

  protected function parseLines()
  {
    $lines = [];
    $line_elements = $this->xmlobj->getElementsByTagName('line');
    foreach ($line_elements as $line) {
      $components = $this->getPointsFromCoords(trim($line->firstChild->nodeValue));
      $lines[] = new LineString($components);
    }
    return $lines;
  }

  /**
   * Parse polygons -
   * @return array
   */
  protected function parsePolygons()
  {
    $polygons = [];
    $poly_elements = $this->xmlobj->getElementsByTagName('polygon');
    foreach ($poly_elements as $poly) {
      if ($poly->hasChildNodes()) {
        $points = $this->getPointsFromCoords(trim($poly->firstChild->nodeValue));
        $exterior_ring = new LineString($points);
        $polygons[] = new Polygon(array($exterior_ring));
        continue;
      }
      // It's an EMPTY polygon
      $polygons[] = new Polygon();
    }
    return $polygons;
  }

  /**
   * Parse boxes
   * Boxes are rendered into polygons
   * @return array
   */
  protected function parseBoxes()
  {
    $polygons = [];
    $box_elements = $this->xmlobj->getElementsByTagName('box');
    foreach ($box_elements as $box) {
      $parts = explode(' ',trim($box->firstChild->nodeValue));
      $components = array(
        new Point($parts[3], $parts[2]),
        new Point($parts[3], $parts[0]),
        new Point($parts[1], $parts[0]),
        new Point($parts[1], $parts[2]),
        new Point($parts[3], $parts[2]),
      );
      $exterior_ring = new LineString($components);
      $polygons[] = new Polygon(array($exterior_ring));
    }
    return $polygons;
  }

  /**
   * Parse circles -
   * @return array
   */
  protected function parseCircles()
  {
    // todo: Add good support once we have circular-string geometry support
    $points = [];
    $circle_elements = $this->xmlobj->getElementsByTagName('circle');
    foreach ($circle_elements as $circle) {
      $parts = explode(' ',trim($circle->firstChild->nodeValue));
      $points[] = new Point($parts[1], $parts[0]);
    }
    return $points;
  }

  /**
   * Geometry to geo rss -
   * @param $geom
   * @return bool|string
   */
  protected function geometryToGeoRSS($geom)
  {
    $type = strtolower($geom->getGeomType());
    switch ($type) {
      case 'point':
        return $this->pointToGeoRSS($geom);
      case 'linestring':
        return $this->linestringToGeoRSS($geom);
      case 'polygon':
        return $this->PolygonToGeoRSS($geom);
        break;
      case 'multipoint':
      case 'multilinestring':
      case 'multipolygon':
      case 'geometrycollection':
        return $this->collectionToGeoRSS($geom);
    }
    return false;
  }

  /**
   * Point to geo rss -
   * @param Geometry $geom
   * @return string
   */
  private function pointToGeoRSS(Geometry $geom)
  {
    $out = '<'.$this->nss.'point>';
    if (!$geom->isEmpty()) {
      $out .= $geom->getY().' '.$geom->getX();
    }
    $out .= '</'.$this->nss.'point>';
    return $out;
  }

  /**
   * Linestring to geo rss -
   * @param $geom
   * @return string
   */
  private function linestringToGeoRSS($geom)
  {
    $output = '<'.$this->nss.'line>';
    foreach ($geom->getComponents() as $k => $point) {
      $output .= $point->getY().' '.$point->getX();
      if ($k < ($geom->numGeometries() -1)) $output .= ' ';
    }
    $output .= '</'.$this->nss.'line>';
    return $output;
  }

  /**
   * Polygon to geo rss -
   * @param $geom
   * @return string
   */
  private function polygonToGeoRSS($geom)
  {
    $output = '<'.$this->nss.'polygon>';
    $exterior_ring = $geom->exteriorRing();
    foreach ($exterior_ring->getComponents() as $k => $point) {
      $output .= $point->getY().' '.$point->getX();
      if ($k < ($exterior_ring->numGeometries() -1)) $output .= ' ';
    }
    $output .= '</'.$this->nss.'polygon>';
    return $output;
  }

  /**
   * Collection to geo rss -
   * @param $geom
   * @return string
   */
  public function collectionToGeoRSS($geom)
  {
    $geoRss = '<'.$this->nss.'where>';
    $components = $geom->getComponents();
    foreach ($components as $comp) {
      $geoRss .= $this->geometryToGeoRSS($comp);
    }
    $geoRss .= '</'.$this->nss.'where>';
    return $geoRss;
  }
}
