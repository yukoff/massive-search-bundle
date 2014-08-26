<?php

namespace Massive\Bundle\SearchBundle\Search\Event;

use Symfony\Component\EventDispatcher\Event;
use Massive\Bundle\SearchBundle\Search\Document;
use Massive\Bundle\SearchBundle\Search\QueryHit;

class HitEvent extends Event
{
    protected $hit;
    protected $documentReflection;

    public function __construct(QueryHit $hit, \ReflectionClass $documentReflection)
    {
        $this->hit = $hit;
        $this->documentReflection = $documentReflection;
    }

    public function getHit() 
    {
        return $this->hit;
    }

    public function getDocumentReflection() 
    {
        return $this->documentReflection;
    }
    
}
