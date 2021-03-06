<?php

namespace Shmock\ClassBuilder;

/**
 * @package ClassBuilder
 * JoinPoints represent a single invocation of a method. JoinPoints may
 * telescope - that is, calling `$joinPoint->execute()` may not directly
 * trigger the underlying method, but instead invoke the next Decorator
 * in the chain.
 */
interface JoinPoint
{
    /**
     * @return string|object the target object or class that is the receiver
     * of this invocation. If this method is static, the target is the name
     * of the class.
     */
    public function target();

    /**
     * @return string The name of the method that is being invoked.
     */
    public function methodName();

    /**
     * @return array the list of arguments that is currently slated to be sent
     * to the underlying function
     */
    public function arguments();

    /**
     * Alter the arguments to be sent to the underlying method
     * @param  array $newArguments
     * @return void
     */
    public function setArguments(array $newArguments);

    /**
     * @return mixed|null invokes the underlying method or another JoinPoint handler.
     */
    public function execute();
}
