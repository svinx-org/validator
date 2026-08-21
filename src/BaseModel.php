<?php

namespace Svinx\Validator;

class BaseModel {
    public function __construct(...$options) {
        $reflectionClass = new \ReflectionClass($this);
        $instance = $reflectionClass->newInstanceArgs();
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);
            if (empty($attributes)) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $route = $attribute->route;
            $methods = $attribute->methods;
            $middlewares = $attribute->middlewares;

            $variable = [$instance, $method->getName()];

            print_r($variable);
        }
    }
}