<?php

declare(strict_types=1);

namespace MsgPhp\User\Infra\Doctrine\Event;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use MsgPhp\User\Entity\Username;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class UsernameListener
{
    private $mapping;
    private $updateUsernames = [];

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        if (!isset($this->mapping[($metadata = $event->getClassMetadata())->getName()])) {
            return;
        }

        $metadata->addEntityListener('prePersist', self::class, 'add');
        $metadata->addEntityListener('preUpdate', self::class, 'update');
        $metadata->addEntityListener('preRemove', self::class, 'remove');
    }

    /**
     * @param object $entity
     */
    public function add($entity, LifecycleEventArgs $event): void
    {
        $em = $event->getEntityManager();

        foreach ($this->getUsernames($entity, $em) as $username) {
            $em->persist($username);
        }
    }

    /**
     * @param object $entity
     */
    public function update($entity, PreUpdateEventArgs $event): void
    {
        foreach ($this->getMapping($entity, $event->getEntityManager()) as $mapping) {
            if (!$event->hasChangedField($mapping['field'])) {
                continue;
            }

            $this->updateUsernames[$event->getOldValue($mapping['field'])] = $event->getNewValue($mapping['field']);
        }
    }

    /**
     * @param object $entity
     */
    public function remove($entity, LifecycleEventArgs $event): void
    {
        $em = $event->getEntityManager();
        $metadata = $em->getClassMetadata(get_class($entity));

        foreach ($this->getMapping($entity, $em) as $mapping) {
            if (!isset($mapping['mapped_by'])) {
                continue;
            }

            $this->updateUsernames[$metadata->getFieldValue($entity, $mapping['field'])] = null;
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if (!$this->updateUsernames) {
            return;
        }

        $em = $event->getEntityManager();

        /** @var Username[] $usernames */
        $usernames = $em->getRepository(Username::class)->findBy(['username' => array_keys($this->updateUsernames)]);

        foreach ($usernames as $username) {
            $em->remove($username);

            if (isset($this->updateUsernames[$usernameValue = (string) $username])) {
                $em->persist(new Username($username->getUser(), $this->updateUsernames[$usernameValue]));
            }
        }

        $this->updateUsernames = [];

        $em->flush();
    }

    /**
     * @param object $entity
     *
     * @return Username[]
     */
    private function getUsernames($entity, EntityManagerInterface $em): iterable
    {
        $metadata = $em->getClassMetadata(get_class($entity));

        foreach ($this->getMapping($entity, $em) as $mapping) {
            $user = isset($mapping['mapped_by']) ? $metadata->getFieldValue($entity, $mapping['mapped_by']) : $entity;

            yield new Username($user, $metadata->getFieldValue($entity, $mapping['field']));
        }
    }

    private function getMapping($entity, EntityManagerInterface $em): array
    {
        if (isset($this->mapping[$class = ClassUtils::getClass($entity)])) {
            return $this->mapping[$class];
        }

        foreach ($em->getClassMetadata($class)->parentClasses as $parent) {
            if (isset($this->mapping[$parent])) {
                return $this->mapping[$parent];
            }
        }

        throw new \LogicException(sprintf('No username mapping available for entity "%s".', $class));
    }
}
