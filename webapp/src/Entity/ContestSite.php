<?php declare(strict_types=1);
// based on TeamCategory.php
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contest sites (for contests that run across multiple locations)
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Contest sites (for contests that run across multiple locations)',
])]
#[ORM\Index(columns: ['sortorder'], name: 'sortorder')]
class ContestSite extends BaseApiEntity implements Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Contest site ID', 'unsigned' => true])]
    #[Serializer\SerializedName('id')]
    #[Serializer\Type('string')]
    protected ?int $siteid = null;

    #[ORM\Column(options: ['comment' => 'Descriptive name'])]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(
        type: 'tinyint',
        options: ['comment' => 'Where to sort this site', 'unsigned' => true, 'default' => 0]
    )]
    #[Assert\GreaterThanOrEqual(0, message: 'Only non-negative sortorders are supported')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private int $sortorder = 0;

    #[ORM\Column(options: ['comment' => 'Does this site accept new registrations?', 'default' => 1])]
    #[Serializer\Exclude]
    private bool $active = true;

    public function __toString(): string
    {
        return $this->name;
    }

    public function setSiteid(int $siteid): ContestSite
    {
        $this->siteid = $siteid;
        return $this;
    }

    public function getSiteid(): ?int
    {
        return $this->siteid;
    }

    public function setName(string $name): ContestSite
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getShortDescription(): ?string
    {
        return $this->getName();
    }

    public function setSortorder(int $sortorder): ContestSite
    {
        $this->sortorder = $sortorder;
        return $this;
    }

    public function getSortorder(): int
    {
        return $this->sortorder;
    }

    public function setActive(bool $active): ContestSite
    {
        $this->active = $active;
        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }
}
