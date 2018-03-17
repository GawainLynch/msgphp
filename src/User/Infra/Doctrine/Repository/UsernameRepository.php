<?php

declare(strict_types=1);

namespace MsgPhp\User\Infra\Doctrine\Repository;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use MsgPhp\Domain\{DomainCollectionInterface, DomainIdentityHelper};
use MsgPhp\Domain\Factory\DomainCollectionFactory;
use MsgPhp\Domain\Infra\Doctrine\DomainEntityRepositoryTrait;
use MsgPhp\User\Entity\{User, Username};
use MsgPhp\User\Repository\UsernameRepositoryInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class UsernameRepository implements UsernameRepositoryInterface
{
    use DomainEntityRepositoryTrait {
        __construct as private __parent_construct;
    }

    private $alias = 'username';
    private $targetMapping;

    public function __construct(string $class, EntityManagerInterface $em, array $targetMapping = [], DomainIdentityHelper $identityHelper = null)
    {
        $this->__parent_construct($class, $em, $identityHelper);

        $this->targetMapping = $targetMapping;
    }

    /**
     * @return DomainCollectionInterface|Username[]
     */
    public function findAll(int $offset = 0, int $limit = 0): DomainCollectionInterface
    {
        $qb = $this->createQueryBuilder();
        $qb->indexBy($this->alias, 'username');

        return $this->createResultSet($qb->getQuery(), $offset, $limit);
    }

    /**
     * @return DomainCollectionInterface|Username[]
     */
    public function findAllFromTargets(int $offset = 0, int $limit = 0): DomainCollectionInterface
    {
        if (!$this->targetMapping) {
            throw new \LogicException('No username mapping available.');
        }

        $qb = $this->em->createQueryBuilder();
        $targetInfo = $aliases = [];
        foreach ($this->targetMapping as $class => $mappings) {
            $metadata = $this->em->getClassMetadata($class);
            $alias = $aliases[$class] ?? ($aliases[$class] = 'target'.count($aliases));

            foreach ($mappings as $mapping) {
                $fields = array_flip($idFields = $metadata->getIdentifierFieldNames());

                if (!isset($fields[$mapping['field']])) {
                    $fields[$mapping['field']] = true;
                }

                if (isset($mapping['mapped_by'])) {
                    if (!isset($fields[$mapping['mapped_by']])) {
                        $fields[$mapping['mapped_by']] = true;
                    }

                    $userField = $mapping['mapped_by'];
                } else {
                    $userField = null;
                }

                $qb->addSelect(sprintf('partial %s.{%s}', $alias, implode(', ', array_keys($fields))));
                $qb->from($mapping['target'], $alias);

                $targetInfo[$class][] = ['user_field' => $userField, 'username_field' => $mapping['field']];

                foreach ((array) $metadata->discriminatorMap as $discriminatorClass) {
                    if (isset($targetInfo[$discriminatorClass]) || isset($this->targetMapping[$discriminatorClass])) {
                        continue;
                    }

                    $targetInfo[$discriminatorClass] = $targetInfo[$class];
                }
            }
        }

        $result = [];
        foreach ($qb->getQuery()->getResult() as $targetEntity) {
            $metadata = $this->em->getClassMetadata($class = ClassUtils::getRealClass(get_class($targetEntity)));

            foreach ($targetInfo[$class] as $info) {
                if ($targetEntity instanceof User) {
                    $user = $targetEntity;
                } elseif (isset($info['user_field'])) {
                    $user = $metadata->getFieldValue($targetEntity, $info['user_field']);

                    if (null === $user) {
                        continue;
                    }

                    if (!$user instanceof User) {
                        throw new \LogicException(sprintf('Field "%s.%s" must return an instance of "%s" or null, got "%s".', $class, $info['user_field'], User::class, is_object($user) ? get_class($user) : gettype($user)));
                    }
                } else {
                    throw new \LogicException(sprintf('No user field mapped for entity "%s".', $class));
                }

                if (null === $username = $metadata->getFieldValue($targetEntity, $info['username_field'])) {
                    continue;
                }

                $result[] = new $this->class($user, $username);
            }
        }

        $result = DomainCollectionFactory::create($result);

        return $offset || $limit ? $result->slice($offset, $limit) : $result;
    }

    public function find(string $username): Username
    {
        return $this->doFind($username);
    }

    public function exists(string $username): bool
    {
        return $this->doExists($username);
    }

    public function save(Username $user): void
    {
        $this->doSave($user);
    }

    public function delete(Username $user): void
    {
        $this->doDelete($user);
    }
}
