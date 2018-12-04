<?php

namespace CFPP\Shipping\ShippingMethods;

use CFPP\Shipping\ShippingMethods\Packages\CorreiosPackage;
use CFPP\Exceptions\PackageException;

class Package
{
    /**
     * @param \WC_Product $product
     * @param $quantity
     * @param \WC_Shipping_Method $shipping_method
     * @return array|mixed
     * @throws PackageException
     */
    public static function makeFrom(\WC_Product $product, $quantity, \WC_Shipping_Method $shipping_method)
    {
        $instance = new self;
        $package_handler = $instance->getPackageHandlerForShippingMethod($shipping_method);
        $package = $package_handler->generatePackage($product, $quantity);

        /** Fix for Correios Shipping Methods bug */
        if ($shipping_method instanceof \WC_Correios_Shipping) {
            $package = $instance->adjustPackageForCorreiosShippingMethods($package);
        }

        $instance->validatePackage($package);
        return $package;
    }

    /**
     * Returns the package handler for given Shipping Method
     *
     * @param \WC_Shipping_Method $shipping_Method
     * @return PackageInterface
     */
    protected function getPackageHandlerForShippingMethod(\WC_Shipping_Method $shipping_Method)
    {
        // Defaults to CorreiosPackage by now.
        return new CorreiosPackage;
    }

    /**
     * Validates a Package
     *
     * @param $package
     * @throws PackageException
     */
    protected function validatePackage($package)
    {
        // Only positive numeric dimensions
        foreach ($package as $package_dimension) {
            if ( ! is_numeric($package_dimension) || (float) $package_dimension <= 0) {
                throw PackageException::invalid_package();
            }
        }
    }

    /**
     * WooCommerce Correios has a bug that inverts height
     * and length dimensions. We must do it too.
     *
     * @see https://github.com/claudiosanches/woocommerce-correios/pull/130
     */
    protected function adjustPackageForCorreiosShippingMethods($package)
    {
        $height = $package['height'];
        $length = $package['length'];

        $package['height'] = $length;
        $package['length'] = $height;

        return $package;
    }
}
