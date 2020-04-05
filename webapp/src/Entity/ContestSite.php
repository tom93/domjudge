<?php declare(strict_types=1);
// based on TeamCategory.php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contest sites (for contests that run across multiple locations)
 * @ORM\Entity()
 * @ORM\Table(
 *     name="contest_site",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Contest sites (for contests that run across multiple locations)"},
 *     indexes={@ORM\Index(name="sortorder", columns={"sortorder"})})
 */
class ContestSite extends BaseApiEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="siteid", length=4,
     *     options={"comment"="Contest site ID","unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected ?int $siteid = null;

    /**
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Descriptive name"}, nullable=false)
     * @Assert\NotBlank()
     */
    private string $name;

    /**
     * @ORM\Column(type="tinyint", name="sortorder",
     *     options={"comment"="Where to sort this site",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     * @Assert\GreaterThanOrEqual(0, message="Only non-negative sortorders are supported")
     */
    private int $sortorder = 0;

    /**
     * @ORM\Column(type="boolean", name="active",
     *     options={"comment"="Does this site accept new registrations?",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $active = true;

    /**
     * @ORM\OneToMany(targetEntity="Team", mappedBy="site")
     * @Serializer\Exclude()
     */
    private Collection $teams;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
    }

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

    public function getTeams(): Collection
    {
        return $this->teams;
    }
}
