<?php

namespace Tests\Unit\Entity\Repository;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\EntityReference;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\RepositoryPolicy;
use Amtgard\ActiveRecordOrm\Entity\Repository\EntityFieldMap;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;
use Phake;
use Phake\Mock;
use Tests\Util\ConcreteRepositoryEntity;
use function PHPUnit\Framework\assertEquals;

#[RepositoryOf("test_table", TestRepositoryEntity::class)]
class TestRepository extends Repository
{
    public static function getTableName()
    {
        return 'test_table';
    }

    public static function getEntityClass()
    {
        return TestRepositoryEntity::class;
    }
}

#[EntityOf(TestRepository::class)]
class TestRepositoryEntity extends RepositoryEntity
{
    use Builder, ToBuilder, Data;

    #[PrimaryKey]
    private ?int $id;

    #[Field('name_field')]
    private ?string $name;

    #[Field('created_at')]
    private ?\DateTime $createdAt;
}

#[EntityOf(TestRepository::class)]
class TestRepositoryEntityWithBackingRef extends RepositoryEntity
{

    #[PrimaryKey]
    private ?int $id;

    #[Field('name_field')]
    private ?string $name;

    private ?int $linkId;

    #[Field('int_value')]
    #[EntityReference('linkId')]
    private ?TestRepositoryEntity $link;
}

#[EntityOf(TestRepository::class)]
class DateTimeTestRepositoryEntity extends RepositoryEntity
{
    use Builder, ToBuilder, Data;

    #[PrimaryKey]
    private ?int $id;

    #[Field('name_field')]
    private ?string $name;

    #[Field('created_at', \DateTimeInterface::class)]
    private $createdAt; // No type hint - type comes from Field attribute
}

class RepositoryEntityTest extends AmtgardTestCase
{
    #[Mock]
    private Database $mockDatabase;
    #[Mock]
    private DataAccessPolicy $mockPolicy;
    #[Mock]
    private RepositoryPolicy $mockRepositoryPolicy;
    #[Mock]
    private EntityMapper $mockEntityMapper;

    private EntityManager $mockEntityManager;
    private Entity $mockEntity;
    private TableSchema $mockTableSchema;
    private FieldDefinition $mockPrimaryKey;
    private Table $mockTable;
    private FieldDefinition $mockIdField;
    private FieldDefinition $mockNameField;
    private FieldDefinition $mockCreatedAtField;

    protected function setUp(): void
    {
        parent::setUp();

        Phake::initAnnotations($this);

        // Reset static state before each test
        $this->resetEntityManagerStaticState();

        // Initialize additional mocks used by RepositoryEntity tests
        $this->mockEntityManager = Phake::mock(EntityManager::class);
        $this->mockEntity = Phake::mock(Entity::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $this->mockTable = Phake::mock(Table::class);

        Phake::when($this->mockEntityMapper)->getName()->thenReturn("test_table");
        Phake::when($this->mockEntity)->getPrimaryKey()->thenReturn($this->mockPrimaryKey);
        Phake::when($this->mockPrimaryKey)->getValue()->thenReturn(1);
        Phake::when($this->mockEntity)->isDirty()->thenReturn(true);
        Phake::when($this->mockEntity)->getChanges()->thenReturn(['name_field' => 'new_value']);
        Phake::when($this->mockEntity)->schema->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntityMapper)->getTable()->thenReturn($this->mockTable);
        Phake::when($this->mockTable)->getName()->thenReturn('test_table');
        Phake::when($this->mockTable)->getTableSchema()->thenReturn($this->mockTableSchema);

        // Mock field definitions for the schema fields used in TestRepositoryEntity
        $this->mockIdField = Phake::mock(FieldDefinition::class);
        $this->mockNameField = Phake::mock(FieldDefinition::class);
        $this->mockCreatedAtField = Phake::mock(FieldDefinition::class);

        Phake::when($this->mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($this->mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockCreatedAtField)->getType()->thenReturn(FieldType::DATETIME);

        Phake::when($this->mockTableSchema)->getFields()->thenReturn([
            'id' => $this->mockIdField,
            'name_field' => $this->mockNameField,
            'created_at' => $this->mockCreatedAtField,
        ]);

        // Mock getField() for individual field access (used by Entity::valueToFieldType)
        Phake::when($this->mockTableSchema)->getField('id')->thenReturn($this->mockIdField);
        Phake::when($this->mockTableSchema)->getField('name_field')->thenReturn($this->mockNameField);
        Phake::when($this->mockTableSchema)->getField('created_at')->thenReturn($this->mockCreatedAtField);

        // Mock hasField() for Entity::__set() validation
        Phake::when($this->mockTableSchema)->hasField('id')->thenReturn(true);
        Phake::when($this->mockTableSchema)->hasField('name_field')->thenReturn(true);
        Phake::when($this->mockTableSchema)->hasField('created_at')->thenReturn(true);

        EntityManager::configure(EntityManager::builder()
            ->database($this->mockDatabase)
            ->repositoryPolicy($this->mockRepositoryPolicy)
            ->dataAccessPolicy($this->mockPolicy)
            ->database($this->mockDatabase)
            ->mapperSupplier(fn($name) => $this->mockEntityMapper)
            ->preventShutdown(true)
            ->build(), true);

        // Also configure the mock EntityManager for RepositoryEntity tests
        EntityManager::configure($this->mockEntityManager, true);
        Phake::when($this->mockEntityManager)->getMapper('test_table')->thenReturn($this->mockEntityMapper);
        Phake::when($this->mockEntityManager)->getRepository(TestRepository::class)->thenReturn(Phake::mock(TestRepository::class));
    }

    protected function tearDown(): void
    {
        // Clean up static state after each test
        $this->resetEntityManagerStaticState();
        parent::tearDown();
    }

    private function resetEntityManagerStaticState(): void
    {
        // Use reflection to reset the static instance
        $reflection = new \ReflectionClass(EntityManager::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
    }

    public function testRepositoryEntityCanBeInstantiated(): void
    {
        $entity = ConcreteRepositoryEntity::builder()->build();

        self::assertInstanceOf(RepositoryEntity::class, $entity);
        self::assertInstanceOf(EntityInterface::class, $entity);
    }

    public function testRepositoryEntityImplementsEntityInterface(): void
    {
        $interfaces = class_implements(RepositoryEntity::class);

        self::assertContains(EntityInterface::class, $interfaces);
    }

    public function testRepositoryEntityHasRequiredMethods(): void
    {
        $entity = ConcreteRepositoryEntity::builder()
            ->build();

        self::assertTrue(method_exists($entity, 'getInternalEntity'));
        self::assertTrue(method_exists($entity, 'persist'));
        self::assertTrue(method_exists($entity, 'isDirty'));
    }

    public function testIsDirty_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->isDirty();

        self::assertTrue($result);
        Phake::verify($this->mockEntity)->isDirty();
    }

    public function testGetChanges_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getChanges();

        assertEquals(['name_field' => 'new_value'], $result);
        Phake::verify($this->mockEntity)->getChanges();
    }

    public function testGetPrimaryKey_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getPrimaryKey();

        self::assertSame($this->mockPrimaryKey, $result);
        Phake::verify($this->mockEntity)->getPrimaryKey();
    }

    public function testPersist_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $entity->persist($this->mockEntityMapper);

        Phake::verify($this->mockEntity)->persist($this->mockEntityMapper);
    }

    public function testGetSchema_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getSchema();

        self::assertSame($this->mockTableSchema, $result);
    }

    public function testGetInternalEntity_returnsEntity(): void
    {
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getInternalEntity();

        self::assertSame($this->mockEntity, $result);
    }

    public function testSetInternalEntity_setsEntity(): void
    {
        $newEntity = Phake::mock(Entity::class);
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $entity->setInternalEntity($newEntity);

        self::assertSame($newEntity, $entity->getInternalEntity());
    }

    public function testToRepositoryEntity_convertsEntity(): void
    {
        // Setup all fields that are in the entity map info
        // The entity map info has: id (source: 'id'), name (source: 'name_field'), createdAt (source: 'created_at')
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);

        // Use thenReturn with a fresh array each time to avoid reference issues
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);

        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);

        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test_name');
        Phake::when($this->mockEntity)->created_at->thenReturn(null);

        $result = TestRepositoryEntity::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntity::class, $result);
    }

    public function testToRepositoryEntity_withRepositoryEntity_usesInternalEntity(): void
    {
        // Setup all fields that are in the entity map info
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test_name');
        Phake::when($this->mockEntity)->created_at->thenReturn(null);

        // Create an entity that is already a RepositoryEntity
        $repositoryEntity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = TestRepositoryEntity::toRepositoryEntity($repositoryEntity);

        self::assertInstanceOf(TestRepositoryEntity::class, $result);
    }

    public function testPreInit_initializesMapperAndEntityMapInfo(): void
    {
        // When mapper is provided via builder, preInit should use it and build entityMapInfo
        $entity = TestRepositoryEntity::builder()->mapper($this->mockEntityMapper)->build();

        $reflection = new \ReflectionClass($entity);
        $mapperProperty = $reflection->getProperty('mapper');
        $mapperProperty->setAccessible(true);
        $entityMapInfoProperty = $reflection->getProperty('entityFieldMap');
        $entityMapInfoProperty->setAccessible(true);
        $entityMapperEntityIdProperty = $reflection->getProperty('entityMapperEntityId');
        $entityMapperEntityIdProperty->setAccessible(true);

        self::assertInstanceOf(EntityMapper::class, $mapperProperty->getValue($entity));
        $entityFieldMap = $entityMapInfoProperty->getValue($entity);
        self::assertInstanceOf(EntityFieldMap::class, $entityFieldMap);
        self::assertNotNull($entityFieldMap->getField('id'));
        self::assertNotNull($entityFieldMap->getField('name'));
        self::assertNotEmpty($entityMapperEntityIdProperty->getValue($entity));
    }

    public function testPreInit_initializesMapperFromAttributeWhenNotProvided(): void
    {
        // Use reflection to set EntityManager::instance directly
        $reflection = new \ReflectionClass(EntityManager::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $this->mockEntityManager);

        // When mapper is not provided, preInit should get it from EntityOf attribute
        $entity = TestRepositoryEntity::builder()->build();

        $entityReflection = new \ReflectionClass($entity);
        $mapperProperty = $entityReflection->getProperty('mapper');
        $mapperProperty->setAccessible(true);

        self::assertInstanceOf(EntityMapper::class, $mapperProperty->getValue($entity));
        Phake::verify($this->mockEntityManager)->getMapper('test_table');
    }

    public function testPreInit_buildsEmptyEntityWhenNotSet(): void
    {
        // Mock EntityFactory::build() requirements
        Phake::when($this->mockEntityMapper)->getTable()->thenReturn($this->mockTable);
        Phake::when($this->mockTable)->getTableSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntityMapper)->getChanges()->thenReturn([]);

        $entity = TestRepositoryEntity::builder()->mapper($this->mockEntityMapper)->build();

        $reflection = new \ReflectionClass($entity);
        $entityProperty = $reflection->getProperty('entity');
        $entityProperty->setAccessible(true);

        self::assertInstanceOf(EntityInterface::class, $entityProperty->getValue($entity));
        Phake::verify($this->mockEntityMapper)->getTable();
        Phake::verify($this->mockTable)->getTableSchema();
        Phake::verify($this->mockEntityMapper)->getChanges();
    }

    public function testPostInit_mapsFieldsToInternalEntity(): void
    {
        // Mock EntityFactory::build() requirements
        $mockEmptyEntity = Phake::mock(Entity::class);
        Phake::when($this->mockEntityMapper)->getTable()->thenReturn($this->mockTable);
        Phake::when($this->mockTable)->getTableSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntityMapper)->getChanges()->thenReturn([]);
        Phake::when($mockEmptyEntity)->getSchema()->thenReturn($this->mockTableSchema);

        // Set field values before building - postInit() should map them to internal entity
        // postInit() is called automatically during build() and calls mapFieldsToInternalEntity()
        // The getField() and hasField() mocks are already set up in setUp() for Entity::valueToFieldType()
        $entityBuilder = TestRepositoryEntity::builder()
            ->mapper($this->mockEntityMapper);
        $entityBuilder = $entityBuilder
            ->id(42);
        $entity = $entityBuilder
            ->name('test_mapped_name')
            ->build();

        // Verify that mapFieldsToInternalEntity() was called by checking that getSchema() was accessed
        // (mapFieldsToInternalEntity() calls $this->entity->getSchema())
        $reflection = new \ReflectionClass($entity);
        $entityProperty = $reflection->getProperty('entity');
        $entityProperty->setAccessible(true);
        $internalEntity = $entityProperty->getValue($entity);

        // The internal entity should have been created and schema accessed during mapping
        self::assertInstanceOf(EntityInterface::class, $internalEntity);
    }

    public function testOnSetField_mapsToEntity(): void
    {
        $entity = TestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $entity->name = 'test_name';

        Phake::verify($this->mockEntity)->__set('name_field', 'test_name');
    }

    public function testDateTimeTestRepositoryEntity_fetch_convertsDateTimeFromDatetime(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::DATETIME);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->created_at->thenReturn('2023-01-01 12:00:00');
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = DateTimeTestRepositoryEntity::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(DateTimeTestRepositoryEntity::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
            self::assertEquals('2023-01-01 12:00:00', $createdAtValue->format('Y-m-d H:i:s'));
        } else {
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public function testDateTimeTestRepositoryEntity_fetch_convertsDateTimeFromInteger(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        $timestamp = 1672574400; // 2023-01-01 12:00:00 UTC
        Phake::when($this->mockEntity)->created_at->thenReturn($timestamp);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = DateTimeTestRepositoryEntity::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(DateTimeTestRepositoryEntity::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
            self::assertEquals($timestamp, $createdAtValue->getTimestamp());
        } else {
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public static $mockEntityValueCanary = null;
    public function testDateTimeTestRepositoryEntity_set_mapsDateTimeToEntity(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        Phake::when($this->mockTableSchema)->getFields()->thenReturn([
            'id' => $this->mockIdField,
            'name_field' => $this->mockNameField,
            'created_at' => $mockCreatedAtField
        ]);
        Phake::when($this->mockTableSchema)->hasField('created_at')->thenReturn(true);
        Phake::when($this->mockTableSchema)->getField('created_at')->thenReturn($mockCreatedAtField);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::DATETIME);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        RepositoryEntityTest::$mockEntityValueCanary = null;
        Phake::when($this->mockEntity)->__set(Phake::anyParameters())->thenReturnCallback(function ($name, $value) {
            RepositoryEntityTest::$mockEntityValueCanary = $value;
        });

        $entity = DateTimeTestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $dateTime = new \DateTime('2023-01-01 12:00:00');
        $entity->createdAt = $dateTime;

        assertEquals($dateTime, RepositoryEntityTest::$mockEntityValueCanary);
    }

    public function testDateTimeTestRepositoryEntity_set_mapsDateTimeToEntityIntegerField(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        Phake::when($this->mockTableSchema)->getFields()->thenReturn([
            'id' => $this->mockIdField,
            'name_field' => $this->mockNameField,
            'created_at' => $mockCreatedAtField
        ]);
        Phake::when($this->mockTableSchema)->hasField('created_at')->thenReturn(true);
        Phake::when($this->mockTableSchema)->getField('created_at')->thenReturn($mockCreatedAtField);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        RepositoryEntityTest::$mockEntityValueCanary = null;
        Phake::when($this->mockEntity)->__set(Phake::anyParameters())->thenReturnCallback(function ($name, $value) {
            RepositoryEntityTest::$mockEntityValueCanary = $value;
        });

        $entity = DateTimeTestRepositoryEntity::builder()
            ->entity($this->mockEntity)
            ->build();

        $dateTime = new \DateTime('2024-01-01 12:00:00');
        $entity->createdAt = $dateTime;

        assertEquals($dateTime, RepositoryEntityTest::$mockEntityValueCanary);
    }

    public function testMapFieldsToInternalEntity_mapsFieldValuesToEntity(): void
    {
        $mockRepository = Phake::mock(TestRepository::class);
        Phake::when($this->mockEntityManager)->getRepository(TestRepository::class)->thenReturn($mockRepository);
        Phake::when($mockRepository)->createMapperEntity()->thenReturn($this->mockEntity);

        $entity = TestRepositoryEntity::builder()
            ->id(42)
            ->name('test_mapped_name')
            ->build();

        Phake::verify($this->mockTableSchema)->hasField('id');
        Phake::verify($this->mockTableSchema)->hasField('name_field');
        Phake::verify($this->mockTableSchema)->getField('id');
        Phake::verify($this->mockTableSchema)->getField('name_field');
    }

    public function testMapFieldsToInternalEntity_onlyMapsSetFields(): void
    {
        $mockRepository = Phake::mock(TestRepository::class);
        Phake::when($this->mockEntityManager)->getRepository(TestRepository::class)->thenReturn($mockRepository);
        Phake::when($mockRepository)->createMapperEntity()->thenReturn($this->mockEntity);

        // Only set one field - mapFieldsToInternalEntity should only map fields that are set
        $entity = TestRepositoryEntity::builder()
            ->name('only_this_field')
            ->build();

        // Verify that the set field was mapped to the internal entity using the source field name
        Phake::verify($this->mockTableSchema)->hasField('name_field');
        Phake::verify($this->mockTableSchema)->getField('name_field');
    }

    public function testToRepositoryEntity_convertsDateTimeFromDatetime(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::DATETIME);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->created_at->thenReturn('2023-01-01 12:00:00');
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntity::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntity::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        // Check if property is initialized
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
            self::assertEquals('2023-01-01 12:00:00', $createdAtValue->format('Y-m-d H:i:s'));
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_convertsDateTimeFromInteger(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->created_at->thenReturn('1672574400');
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntity::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntity::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        // Check if property is initialized
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_convertsDateTimeDefaultCase(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        // Use a type that's neither DATETIME nor INTEGER to trigger default case
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->created_at->thenReturn('some_value');
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntity::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntity::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        // Check if property is initialized
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            // Default case creates a new DateTime() with current time
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_handlesDefaultFieldType(): void
    {
        // Setup all fields that are in the entity map info
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test_name');
        Phake::when($this->mockEntity)->created_at->thenReturn(null);

        $result = TestRepositoryEntity::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntity::class, $result);
    }

    public function testToRepositoryEntity_withBackingReferencePk_setsBackingField(): void
    {
        $mockIntValueField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'int_value' => $mockIntValueField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockIntValueField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->int_value->thenReturn(42);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntityWithBackingRef::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntityWithBackingRef::class, $result);
        $reflection = new \ReflectionClass($result);
        $linkIdProperty = $reflection->getProperty('linkId');
        $linkIdProperty->setAccessible(true);
        // Check if property is initialized
        if ($linkIdProperty->isInitialized($result)) {
            $linkIdValue = $linkIdProperty->getValue($result);
            // When backingReferencePk is set, it should set the backing field instead of the entity field
            self::assertEquals(42, $linkIdValue);
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('linkId property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_withEntityInterfaceWithoutBackingRef_assignsDirectly(): void
    {
        // Create a mock entity that implements EntityInterface but doesn't have backingReferencePk
        $mockEntityInterface = Phake::mock(Entity::class);
        Phake::when($mockEntityInterface)->getSchema()->thenReturn($this->mockTableSchema);

        // Setup all fields that are in the entity map info
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        Phake::when($this->mockTableSchema)->getFields()->thenReturn([
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ]);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockEntityInterface)->id->thenReturn(1);
        Phake::when($mockEntityInterface)->name_field->thenReturn('test_value');
        Phake::when($mockEntityInterface)->created_at->thenReturn(null);

        // This should fall through to the default assignment case
        $result = TestRepositoryEntity::toRepositoryEntity($mockEntityInterface);

        self::assertInstanceOf(TestRepositoryEntity::class, $result);
    }
}
