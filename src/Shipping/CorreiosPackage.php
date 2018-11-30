<?php

namespace CFPP\Shipping;

use CFPP\Exceptions\PackageException;

/**
 * This is a wrapper from Claudio Sanche's WC_Correios_Package class
 *
 * Class Package
 * @package CFPP\Shipping
 */
class CorreiosPackage
{
    /** @var \WC_Product $product */
    protected $product;

    /** @var int $quantity */
    protected $quantity;

    /**
     * Extracts the weight and dimensions from the package.
     *
     * @return array
     */
    protected function getPackageData(\WC_Product $product, $qty) {
        $count  = 0;
        $height = array();
        $width  = array();
        $length = array();
        $weight = array();

        $_height = wc_get_dimension( (float) $product->get_height(), 'cm' );
        $_width  = wc_get_dimension( (float) $product->get_width(), 'cm' );
        $_length = wc_get_dimension( (float) $product->get_length(), 'cm' );
        $_weight = wc_get_weight( (float) $product->get_weight(), 'kg' );

        $height[ $count ] = $_height;
        $width[ $count ]  = $_width;
        $length[ $count ] = $_length;
        $weight[ $count ] = $_weight;

        if ( $qty > 1 ) {
            $n = $count;
            for ( $i = 0; $i < $qty; $i++ ) {
                $height[ $n ] = $_height;
                $width[ $n ]  = $_width;
                $length[ $n ] = $_length;
                $weight[ $n ] = $_weight;
                $n++;
            }
        }

        return array(
            'height' => array_values( $height ),
            'length' => array_values( $length ),
            'width'  => array_values( $width ),
            'weight' => array_sum( $weight ),
        );
    }

    /**
     * Calculates the cubage of all products.
     *
     * @param  array $height Package height.
     * @param  array $width  Package width.
     * @param  array $length Package length.
     *
     * @return int
     */
    protected function cubageTotal( $height, $width, $length ) {
        // Sets the cubage of all products.
        $total       = 0;
        $total_items = count( $height );

        for ( $i = 0; $i < $total_items; $i++ ) {
            $total += $height[ $i ] * $width[ $i ] * $length[ $i ];
        }

        return $total;
    }

    /**
     * Get the max values.
     *
     * @param  array $height Package height.
     * @param  array $width  Package width.
     * @param  array $length Package length.
     *
     * @return array
     */
    protected function getMaxValues( $height, $width, $length ) {
        $find = array(
            'height' => max( $height ),
            'width'  => max( $width ),
            'length' => max( $length ),
        );

        return $find;
    }

    /**
     * Calculates the square root of the scaling of all products.
     *
     * @param  array $height     Package height.
     * @param  array $width      Package width.
     * @param  array $length     Package length.
     * @param  array $max_values Package bigger values.
     *
     * @return float
     */
    protected function calculateRoot( $height, $width, $length, $max_values ) {
        $cubage_total = $this->cubageTotal( $height, $width, $length );
        $root         = 0;
        $biggest      = max( $max_values );

        if ( 0 !== $cubage_total && 0 < $biggest ) {
            // Dividing the value of scaling of all products.
            // With the measured value of greater.
            $division = $cubage_total / $biggest;
            // Total square root.
            $root = round( sqrt( $division ), 1 );
        }

        return $root;
    }

    /**
     * Sets the final cubage.
     *
     * @param  array $height Package height.
     * @param  array $width  Package width.
     * @param  array $length Package length.
     *
     * @return array
     */
    protected function getCubage( $height, $width, $length ) {
        $cubage     = array();
        $max_values = $this->getMaxValues( $height, $width, $length );
        $root       = $this->calculateRoot( $height, $width, $length, $max_values );
        $greatest   = array_search( max( $max_values ), $max_values, true );

        switch ( $greatest ) {
            case 'height' :
                $cubage = array(
                    'height' => max( $height ),
                    'width'  => $root,
                    'length' => $root,
                );
                break;
            case 'width' :
                $cubage = array(
                    'height' => $root,
                    'width'  => max( $width ),
                    'length' => $root,
                );
                break;
            case 'length' :
                $cubage = array(
                    'height' => $root,
                    'width'  => $root,
                    'length' => max( $length ),
                );
                break;

            default :
                $cubage = array(
                    'height' => 0,
                    'width'  => 0,
                    'length' => 0,
                );
                break;
        }

        return $cubage;
    }

    /**
     * Get the package data.
     *
     * @return array
     * @throws PackageException
     */
    public static function generate(\WC_Product $product, $quantity)
    {
        $instance = new self;

        $instance->product = $product;
        $instance->quantity = intval($quantity);

        try {
            $data = $instance->getPackageData($instance->product, $instance->quantity);

            $cubage = $instance->getCubage($data['height'], $data['width'], $data['length']);

            if ( ! $instance->validatePackage($data, $cubage)) {
                throw PackageException::invalid_package();
            }

            $total_cost = $product->get_price() * $quantity;

            return array(
                'total_cost' => $total_cost,
                'height' => $cubage['height'],
                'width'  => $cubage['width'],
                'length' => $cubage['length'],
                'weight' => $data['weight'],
            );
        } catch(PackageException $e) {
            throw new PackageException($e->getMessage());
        }
    }

    /**
     * Validates a Package
     *
     * @param $data
     * @param $cubage
     * @return bool
     */
    private function validatePackage($data, $cubage)
    {
        // Only positive numeric dimensions
        foreach ($cubage as $dimension) {
            if ( ! is_numeric($dimension) || (float) $dimension <= 0) {
                return false;
            }
        }

        // Only positive numeric weight
        if ( ! is_numeric($data['weight']) || (float) $data['weight'] <= 0) {
            return false;
        }

        return true;
    }
}
