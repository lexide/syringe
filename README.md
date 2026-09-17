# Syringe

Syringe allows a [Pimple](https://github.com/silexphp/pimple) DI container to be created and populated with services 
defined in configuration files, in the same fashion as Symfony's [DI module](https://github.com/symfony/dependency-injection).

# Installation

``composer require lexide/syringe``

# Getting Started

The simplest method to create and set up a new Container is to use the `Lexide\Syringe\Syringe` class.

```php
use Lexide\Syringe\Syringe;

$syringe = new Syringe();
// add paths to your configuration files
$syringe->addConfigFile("config/syringe.yml");
$container = $syringe->build();
```

The container will now contain all the services and parameters that are defined in the config files that were added.

# Building a container

Syringe loads service and parameter definitions from config files and uses the information to construct service objects,
injecting them into the Pimple container. This is done lazily, so a service won't be created until it is used.

## Config Files

The config files are loaded individually, validated and normalised into a definitions array. Once fully compiled,
this array can be cached to prevent reprocessing the config files.

Files are added by calling `addConfigFile()` for single files or `addConfigFiles()` for a set of files. Each file can be 
given a namespace, to separate its parameters and services from those in other files.

```php
$syringe = new Lexide\Syringe\Syringe();
$syringe->addConfigFile("services.yml");
$syringe->addConfigFile("parameters.yml");
$syringe->addConfigFile("module.yml", "module-namespace"); # definition keys in this file are prefixed with "module-namespace."
$syringe->addConfigFiles([
    "logger" => "module/logging.yml",
    "database" => "module/database.yml",
    "cache" => "module/caching.yml"
])  # namespace => filepath format 
```

Namespaces are used to avoid name collisions for definition keys, so that two files that both define the parameter `foo` 
don't try to overwrite each other. A definitions inside the file don't need to know anything about the namespace the file
has been assigned; any parameter or service references to other definitions in the same file are automatically resolved. 
Additionally, a definition in another namespace can be referenced directly by using the full namespaced definition key:

```yml
# [foo.yml namespaced as "one"]
---
parameters:
  fooBar: "foo"   # full key name: one.fooBar

# [bar.yml namespaced as "two"]
---
parameters:
  fooBar: "bar"                 # full key name: two.fooBar
  fooBarCopy: "%fooBar%"        # uses the local namespace "two" and resolves to "bar"
  otherFooBar: "%one.fooBar%"   # as a namespaced key, this directly references fooBar in foo.yml, resolving to "foo"
```

As a rule, it is bad practice to create dependencies between namespaces, as done in the example above, unless there is
a guarantee that both namespaces will always be available, such as when a library requires another library, both of 
which define Syringe definitions.

In general, mapping between namespaces should be done in the root namespace (`""`):

```yml
# [baz.yml in the root namespace, using the files from the previous example]
---
parameters:
  two.otherFooBar: "%one.fooBar%"
```

This helps prevents unmanaged dependency chains, circular references and other config bugs that can be hard to track down.

### Config Paths

When loading config files, if the file path is not absolute, Syringe will look for the file in a list of config 
directories that it has been set up with. By default, it will parse the PHP include path and check each directory it 
finds for the requested file, however that can be turned off and specific directories can be added when setting up 
Syringe.

```php
$syringe = new Lexide\Syringe\Syringe();
$syringe->addConfigPath("/var/www/app/config");
$syringe->addConfigPaths([
    "/var/www/app/",
    "/var/www/external-config/"
]);
```

Syringe will check each path in the order it was added, custom paths first, then from the include path. It will stop
looking as soon as it finds a file matching the relative path, so the order the paths are added in is important.

For example, when looking for a file named `services.yml` with two paths `/app/one` and `/app/two` added in that order, 
if the file exists in both locations, only the file `/app/one/services.yml` will be used.

## Validation

By default, Syringe will validate each config file that it parses, to ensure it is in the correct format and has no 
invalid references. If it finds any issues, they are written to a PSR/3 logger as defined by config options. Any issues 
found will cause processing to stop, but there are several config options that will filter out less severe issues.

This syntax validation can also be turned off, to increase performance, however it is then possible for invalid syntax 
to be processed and cause errors.

## Providers

In addition to config files, Syringe allows for definitions to be supplied by provider classes. These can be used to 
inject runtime or environment variables into definitions or to create services that cannot be defined ahead of time.

### Parameter Map

The `ParameterMapProvider` allows for an array of parameters to be directly injected into the definitions array. This is
useful for adding runtime parameters, such as the current timestamp or the process PID

```php
$syringe = new Lexide\Syringe\Syringe();
$parameterMap = new Lexide\Syringe\Provider\ParameterMapProvider([
    "startTime" => microtime(true),
    "processPid" => getmypid(),
    "guid" => custom_guid_function() 
])

$syringe->addProvider($parameterMap, "CustomParameterMap");
```

### Environment Variables

Syringe can import environment variables into the definitions array by using the `EnvironmentVariableProvider`. This 
takes a map of environment variable name to parameter key and will load the values of the environment variables into the 
corresponding key.

```php
$syringe = new Lexide\Syringe\Syringe();
$envVars = new Lexide\Syringe\Provider\EnvironmentVariableProvider([
    "USER" => "process.username",
    "MY_CUSTOM_VAR" => "myCustomVar"
])

$syringe->addProvider($envVars, "EnvironmentVariableMap");
```

The variable map used here can also be passed into build options to automatically add this provider to Syringe.

### Custom Providers

Syringe will accept any class that implements the `Lexide\Syringe\Provider\DefinitionProviderInterface` interface as a 
provider, so it is possible to create custom providers to suit a specific use case:

```php

class MyCustomProvider implements \Lexide\Syringe\Provider\DefinitionProviderInterface
{

    public function __construct(protected string $namespace = "")
    {}

    public function getDefinitions(): array
    {
        $definitions = [
            "parameters" => []
        ];
        // code to create the definitions array
        return $definitions;
    }

    public function getNamespace(): string
    {
        // sets the namespace for the definitions if one is required
        // return an empty string for the root namespace
        return $this->namespace;
    }

    public function getName(): string
    {
        return "my custom provider"
    }

}
```

## Build Options

The `Syringe` class accepts a `Lexide\Syringe\Container\ConfigOptions` instance as the first constructor argument. This
object contains all the options that Syringe uses when compiling and building container config:

| Option name                   | Type              | Default                           | Description                                                                                                                         |
|-------------------------------|-------------------|-----------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `useIncludePath`              | `bool`            | `true`                            | Tells Syringe to use the PHP include path when loading config files                                                                 |
| `applicationDirectory`        | `string`          | none                              | The absolute path of your application's root directory. Used when loading config files and can be injected as a container parameter |
| `applicationDirectoryKey`     | `string`          | `app.dir`                         | The parameter name to inject the value of the `applicationDirectory` option into                                                    |
| `cacheCompiledDefinitions`    | `bool`            | `true`                            | Tells Syringe to check for and set the compiled definitions array in cache                                                          |
| `compiledDefinitionsCacheKey` | `string`          | `"syringe-container-definitions"` | The key that Syringe uses to check and set the compiled definitions array in cache                                                  |
| `compiledDefinitionsCacheTtl` | `int`             | `300`                             | The TTL to use when setting the compiled definitions array into cache                                                               |
| `serviceFactoryClass`         | `string`          | Syringe ServiceFactory class      | The class used to create services from their definitions                                                                            |
| `containerClass`              | `string`          | Pimple container class            | The class of the service container. Must be a Pimple Container or a subclass                                                        |
| `usePsrContainer`             | `bool`            | `false`                           | Tells Syringe to wrap the Pimple container in a class that implements the PSR ContainerInterface                                    |
| `environmentVariableMap`      | `array`           | `[]`                              | A map of environment variable to Syringe parameter name                                                                             |
| `noStubs`                     | `bool`            | `false`                           | Raise an error id any (unaliased) stub service is detected                                                                          |
| `skipSyntaxValidation`        | `bool`            | `false`                           | Disable syntax validation, to increase performance                                                                                  |
| `ignoreCompilationWarnings`   | `bool`            | `false`                           | Filter out compilation warnings                                                                                                     |
| `ignoreAssertionWarnings`     | `bool`            | `false`                           | Filter out assertion warnings                                                                                                       |
| `ignoreAllWarnings`           | `bool`            | `false`                           | Filter out all  warnings                                                                                                            |
| `processAssertions`           | `bool`            | `true`                            | Switch to turn assertions on or off                                                                                                 |
| `errorLogger`                 | `LoggerInterface` | `null`                            | The error logger to use when reporting errors. Must be an instance implementing the PSR/3 LoggerInterface                           |

Each option can be accessed or set by calling a method of the same name on the ContainerOptions object:

```php
$options = new ContainerOptions();
$containerClass = $options->containerClass(); // get the container class from the options
$options->usePsrContainer(true); // set the value for usePsrContainer
```

# Definition Files

By default, Syringe allows definition files to be in JSON or YAML format. Additionally, native PHP files can be used 
so long as they return and array of definitions.

Each file can define parameters, services and tags to inject into the container and these entities can be referenced in
other areas of configuration. Services can also be extended to add method calls or assign tags and assertions can be 
defined to ensure that config is set and has the correct values.

## Parameters

A Parameter is a named, static value, that can be accessed directly from the Container, or injected into other parameters 
or services.

For a config file to define a parameter, it uses the `parameters` key and then states the parameters name and value.

```yml
parameters:
  myParam: "value"
```

Once defined, a parameter can be referenced inside a string value by surrounding its name with the `%` symbol and the 
parameters value will be inserted when the string value is resolved. This can be done in service arguments or in
other parameters, like so:

```yml
parameters:
  firstName: "Joe"
  lastName: "Bloggs"
  fullName: "%firstName% %lastName%"   # fullName resolves to "Joe Bloggs"
```

Parameters can have any scalar or array value. Arrays are resolved recursively; you can set an array of strings to a 
parameter, each of which contain references to other parameters. This works for both values and array keys.
 
```yml
parameters:
  myFirstValue: "first"
  mySecondValue: "second"

  myList:
    - "The first value is %myFirstValue%"
    - "The second value is %mySecondValue%"
        
  myHash:
    "%myFirstValue%": "%mySecondValue%"
```

## Constants and Enums

Quite often, a value set in a PHP constant is required to be injected. Hard coding these value directly into DI config 
is brittle and requires maintenance to keep in sync, which should be avoided where possible.

Syringe solves this problem by allowing PHP constants to be referenced directly in config, by surrounding the constant
name with `^` characters:

```yml
parameters:
  maxIntValue: "^PHP_INT_MAX^"
  custom: "^MY_CUSTOM_CONSTANT^"
  classConstant: "^MyModule\\MyService::CLASS_CONSTANT^"
```

Where class constants are used, you are required to provide the fully qualified class name. As this has to be enclosed
inside a string, all forward slashes must be escaped, as in the example.

Enums are handled in a similar way, using the same syntax. The value that gets injected depends on the type of enum; 
unit enums will inject the enum symbol directly, backed enums with inject their backed value _unless_ the syringe 
definition is bounded with `*` characters.

```yml
parameters:
  enumSymbol: "^MyEnum::Foo^"
  enumValue: "^MyBackedEnum::Bar^" # injects MyBackedEnum::Bar->value
  backedEnumSymbol: "^*MyBackedEnum::Baz*^" # injects the MyBackedEnum::Baz symbol, rather than it's value
```

The `^* ... *^` syntax will also work on unit enums, but it is unnecessary to do so. 

## Services

Services are instances of a class that can have other services, parameters or values injected into them. A config file 
defines services inside the `services` key and gives each entry a `class` key, containing the fully qualified class name
to instantiate.

For classes which have constructor arguments, these can be specified by setting the `arguments` key to a list of values,
parameters or other services, as required by the constructor.

```yml
services:
  myService:
    class: MyModule\MyService
    arguments:
      - "first constructor argument"
      - 12345
      - false
```

### Service injection

Services can have parameters or other services injected into them as method arguments, by referencing a service name 
prefixed with the `@` character. This is done in one of two ways:

#### Constructor injection

Injection can be done when a service is instantiated, by setting references in `arguments` key of a service definition. 
This is typically done for dependencies which are required.

```yml
services:
  injectable:
    class: MyModule\MyDependency

  myService:
    class: MyModule\MyService
    arguments:
      - "@injectable"
      - "%myParam%"
```

#### Setter injection

Services can also be injected by calling a method after the service has been instantiated, passing the dependant service
in as an argument. This form is useful for optional dependencies.

```yml
services:
  injectable:
    class: MyModule\MyDependency

  myService:
    class: MyModule\MyService
    calls:
      - method: "setInjectable"
        arguments:
          - "@injectable"
```

The `calls` key can be used to run any method on a service, not necessarily one to inject a dependency. They are executed
in the order they are defined.

```yml
services:
  myService:
    class: MyModule\MyService
    calls:
      - method: "warmCache"
      - method: "setTimeout"
        arguments: ["%myTimeout%"]
      - method: "setLogger"
        arguments: ["@myLogger"]
```

### Tags

In some cases, you may want to inject all the services of a given type as a method argument. This can be done manually, 
by building a list of service references in config, but maintaining such a list is cumbersome and time-consuming.

The solution is tags; allowing you to tag a service as being part of a collection and then to inject the whole collection
of services in one reference.

A tag is referenced by prefixing its name with the `#` character.

```yml
services:
  logHandler1:
    #...
    tags:
      - tag: "logHandlers"
            
  logHandler2:
    #...
    tags:
      - tag: "logHandlers"
            
  loggerService:
    #...
    arguments:
      - "#logHandlers"
```

When injected into the `loggerService`, the first constructor argument would be a numeric array containing both log handlers.

#### Keys

It is often useful to assign an identifier to a service when it has been tagged, so that the service using it can know
what it is handling, or select a service by name. This is done by adding a `key` when defining the tag

```yml
services:
  logHandler1:
    #...
    tags:
      - tag: "logHandlers"
        key: "default"

  logHandler2:
    #...
    tags:
      - tag: "logHandlers"
        key: "special"

  loggerService:
    # ...
    arguments:
      - "#logHandlers"
```

Once injected into a service, the injected array is keyed by these key values; from the example above, the first argument 
passed to the `loggerService` would be an associative array containing the keys "default" and "special".

#### Ordering

Tag lists can also be sorted, for situations where the order of tagged services is important. Assigning an `order` value
when defining a tag allows for it's position in the list to be determined.

```yml
services:
  processor1:
    #...
    tags:
      - tag: "processors"
        order: 20

  processor2:
    #...
    tags:
      - tag: "processors"
        order: 10
```

Sorting is always done in ascending order so in this example `processor2` would be before `processor1` in the array.

#### Context

In addition, it is possible to add context information to a tag, for situations where a service requires additional 
metadata about a tagged service.

```yml
services:
  myService:
    # ...
    tags:
      - tag: "myTag"
        context:
          name: "my-service"
          package: "my-package"
          subject: "foo"
```

This context information is available to a service it is injected into, but only when using a `TagIterator`

#### TagIterator

Tags are resolved to a `Lexide\Syringe\Tag\TagIterator` when a service is built. If the service type hints an argument 
as an `array`, `mixed` or has no type hint, an array is created from the `TagIterator` by iterating over it and 
resolving its services. However, it is possible to inject the iterator directly by type hinting one of:

* `iterator`
* `\Iterator`
* `\Traversable`
* `\ArrayAccess`
* `\Lexide\Syringe\Tag\TagIterator`

This allows for the iterator to be resolved by the service code manually. Tagged services won't be resolved until 
iterated on, so a TagIterator can be used to lazy load services. Iteration order is preserved and array access is 
available, for both keyed and numeric tags. In addition, the context information for a service can be accessed by calling
`->context()` on the iterator, for the current iteration.

### Factories

If you have a number of services to be available that use the same class or interface, it can be advantageous to abstract
the creation of these services into a factory class, to aid maintenance and reusability.

Syringe provides two methods of using factories in this way; via a call to a static method on the factory class, or by 
calling a method on a separate factory service.

```yml
services:
  newService1:
    class: MyModule\MyService
    factoryClass: MyModule\MyServiceFactory
    factoryMethod: "createdWithStatic" # calls MyServiceFactory::createWithStatic()
        
  newService2:
    class: MyModule\MyService
    factoryService: "@myServiceFactory"
    factoryMethod: "createdWithService" # calls $myServiceFactory->createWithService()
        
  myServiceFactory:
    class: MyModule\MyServiceFactory 
```

If the factory methods require arguments, you can pass them through using the `arguments` key, in the same way you would
for a normal service or a method call.

### Service Aliases

Syringe allows you to alias a service name to point to another definition, using the `aliasOf` key. 
This is useful if you deal with other modules and need to use your own version of a service instead of the module's default one.

```yml
# [foo.yml]
---
services:
  default:
    class: MyModule\DefaultService
    #...

# [bar.yml]
---
services:
  default:
    aliasOf: "@custom"

  custom:
    class: MyModule\MyService
    #...
```

### Abstract Services

Services can often have definitions that are very similar or contain portions that will always be the same. 
As a method to reduce duplicated config, a service's definition can "extend" an abstract definition. This has the effect of 
merging the two definitions together. Any key conflicts take the service's value rather than the one from the abstract, 
however the list of calls is merged rather than overwritten. There is no restriction on what keys you can define in the 
abstract definition.

Abstract definitions have to be marked as `abstract` and cannot be used directly as a service. These definitions can 
extend other abstract definitions in the same way, similar to how inheritance works in OOP.

```yml
services:
  loggable:
    abstract: true
    calls:
      - method: "setLogger"
        arguments: "@logger"

  myService:
    class: MyModule\MyService
    extends: "@loggable"            # this will import the "setLogger" call into this service definition
        
  factoriedService:
    abstract: true
    extends: "@loggable"
    factoryClass: MyModule\MyServiceFactory
    factoryMethod: "create"

  myFactoriedService:
    class: MyModule\MyService
    extends: "@factoriedService"    # imports both the factory config and the "setLogger" call
    arguments:
      - "factoryArgument"
```

### Private Services

For the vast majority of cases, there is no issue with services being accessed from outside the current module. In fact 
this is advantageous as it promotes modular design, reuse of services and code discovery. However, there can be times 
when data security requires that a service be locked down and to not be available to anything outside the control of the
current module.

In such cases, services can be marked as private by adding the `private` key to their definition:

```yml
services:
  myService:
    #...
    private: true   # the "myService" key will not be available in final container
```

Private services will only be available to other services that are defined with the same namespace, usually within the 
same module.

### Stubbed Services

In some cases, you may require an application or external library to inject a service that you don't have information 
on, such as a plugin or adapter that has functionality that doesn't belong in your library.

In order for Syringe to handle these situations, you should create a stub service to act as a placeholder which can be 
aliased later. These serve as a hook or API for other libraries to interact with your code through Syringe.

```yml
# library A
---
services:
  # This service uses the "adapterService" stub
  aService:
    #...
    arguments:
      - "@adapterService"

  adapterService:
    stub: true
        

# library B
---
services:

  myAdapter:
    #...
        
    # alias "myAdapter" to be the service injected into "library_a.aService"
  library_a.adapterService:
    aliasOf: "@myAdapter"
        
```

By themselves, stub services cannot be accessed or injected; they must have been aliased before the service that uses 
them can be created.  

## Imports

When your definition graph becomes large enough, it is often useful to split your configuration into separate files; 
keeping related parameters and services together. This can be done by using the `imports` key:

```yml
imports:
  - "loggers.yml"
  - "users.yml"
  - "report/orders.yml"
  - "report/products.yml"   # File paths are resolved relative to the file they are defined in.
    
services:
  # ...
```

If any imported files contain duplicated keys, the file that is further down the list wins. As the parent file is always
processed last, its services and parameters always take precedence over the imported definitions.

```yml
# [foo.yml]
---
parameters:
  baz: "from foo"

# [bar.yml]
---
imports: 
  - "foo.yml"
    
parameters:
  baz: "from bar"
    
# when bar.yml is loaded into Syringe, the "baz" parameter will have a value of "from bar"
```

## Extensions

There can be times when you need to call setters on a dependent module's services, in order to inject services from your
application config into it, or to add that a dependent module's service to a tag defined in your application.
In order to do this, you need to use the `extensions` key. This allows you to specify the service and provide a list of 
calls to make on it or tags to add it to, essentially appending them to the service's own definition.

```yml
# [foo.yml, aliased with "foo_namespace"]
---
services:
  myService:
    class: MyModule\MyService
    #...

# [bar.yml]
---
services:
  myLogger:
    #...
        
extensions:
  foo_namespace.myService:
    calls: 
      - method: "addLogger"
        arguments: "@myLogger"
    tags:
      - tag: "myApplicationTag"
```

## Reference characters

In order to identify references, the following characters are used:

* `@` - Services
* `%` - Parameters
* `#` - Tags
* `^` - Constants

## Conventions

Syringe does not enforce naming or style conventions, with one exception. A service's name can be any you like (as long 
as it does not start with one of the reference characters) but a config namespace is always separated from a service name
with a `.`, e.g. `myNamespace.serviceName`. For this reason it can be useful to use `.` as a separator in your own 
service names, to "namespace" related services and parameters:

```yml
parameters:
  database.host: "..."
  database.username: "..."
  database.password: "..."
    
services:
  database.client:
    #...
```
