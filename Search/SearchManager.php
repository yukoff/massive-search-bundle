<?php

namespace Massive\Bundle\SearchBundle\Search;

use Massive\Bundle\SearchBundle\Search\AdapterInterface;
use Massive\Bundle\SearchBundle\Search\Document;
use Massive\Bundle\SearchBundle\Search\Field;
use Massive\Bundle\SearchBundle\Search\SearchEvents;
use Massive\Bundle\SearchBundle\Search\Event\HitEvent;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchManager
{
    protected $adapter;
    protected $metadataFactory;
    protected $eventDispatcher;

    public function __construct(AdapterInterface $adapter, MetadataFactory $metadataFactory, EventDispatcherInterface $eventDispatcher)
    {
        $this->adapter = $adapter;
        $this->metadataFactory = $metadataFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function getMetadata($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(sprintf(
                'You must pass an object to the %s method, you passed: %s',
                __METHOD__,
                var_export($object, true)
            ));
        }

        $objectClass = get_class($object);
        $metadata = $this->metadataFactory->getMetadataForClass($objectClass);

        if (null === $metadata) {
            throw new \RuntimeException(sprintf(
                'There is no search mapping for class "%s"',
                $objectClass
            ));
        }

        return $metadata->getOutsideClassMetadata();
    }

    /**
     * Attempt to index the given object
     *
     * @param object $object
     */
    public function index($object)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        $metadata = $this->getMetadata($object);

        $indexName = $metadata->getIndexName();

        $idField = $metadata->getIdField();
        $urlField = $metadata->getUrlField();
        $titleField = $metadata->getTitleField();
        $descriptionField = $metadata->getDescriptionField();

        $fields = $metadata->getFieldMapping();

        $document = new Document();
        $document->setId($accessor->getValue($object, $idField));
        $document->setClass($metadata->name);

        if ($urlField) {
            $url = $accessor->getValue($object, $urlField);
            if ($url) {
                $document->setUrl($accessor->getValue($object, $urlField));
            }
        }

        if ($titleField) {
            $title = $accessor->getValue($object, $titleField);
            if ($title) {
                $document->setTitle($accessor->getValue($object, $titleField));
            }
        }

        if ($descriptionField) {
            $description = $accessor->getValue($object, $descriptionField);
            if ($description) {
                $document->setDescription($accessor->getValue($object, $descriptionField));
            }
        }

        foreach ($fields as $fieldName => $fieldMapping) {
            $document->addField(Field::create($fieldName, $accessor->getValue($object, $fieldName), $fieldMapping['type']));
        }

        $this->adapter->index($document, $indexName);
    }

    /**
     * Search with the given query string
     */
    public function search($string, $indexNames = null)
    {
        if (null === $indexNames) {
            throw new \Exception('Not implemented yet');
        }

        $indexNames = (array) $indexNames;

        $hits = $this->adapter->search($string, $indexNames);

        $reflections = array();
        foreach ($hits as $hit) {
            $document = $hit->getDocument();

            // we need a reflection instance of the document in event listeners
            if (!isset($metas[$document->getClass()])) {
                $reflections[$document->getClass()] = new \ReflectionClass($document->getClass());
            }

            $this->eventDispatcher->dispatch(SearchEvents::HIT, new HitEvent($hit, $reflections[$document->getClass()]));
        }

        return $hits;
    }
}
