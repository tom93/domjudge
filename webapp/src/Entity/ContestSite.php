<?php declare(strict_types=1);
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
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Contest sites (for contests that run across multiple locations)"},
 *     indexes={@ORM\Index(name="sortorder", columns={"sortorder"})})
 */
class ContestSite extends BaseApiEntity
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="siteid", length=4,
     *     options={"comment"="Contest site ID","unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $siteid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Descriptive name"}, nullable=false)
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @var int
     * @ORM\Column(type="tinyint", name="sortorder", length=1,
     *     options={"comment"="Where to sort this site",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     * @Assert\GreaterThanOrEqual(0, message="Only non-negative sortorders are supported")
     */
    private $sortorder = 0;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="active",
     *     options={"comment"="Does this site accept new registrations?",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $active = true;

    /**
     * @ORM\OneToMany(targetEntity="Team", mappedBy="site")
     * @Serializer\Exclude()
     */
    private $teams;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->teams = new ArrayCollection();
    }

    /**
    * To String
    */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Set siteid
     *
     * @param int $siteid
     *
     * @return ContestSite
     */
    public function setSiteid(int $siteid)
    {
        $this->siteid = $siteid;
        return $this;
    }

    /**
     * Get siteid
     *
     * @return integer
     */
    public function getSiteid()
    {
        return $this->siteid;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return ContestSite
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set sortorder
     *
     * @param integer $sortorder
     *
     * @return ContestSite
     */
    public function setSortorder($sortorder)
    {
        $this->sortorder = $sortorder;

        return $this;
    }

    /**
     * Get sortorder
     *
     * @return integer
     */
    public function getSortorder()
    {
        return $this->sortorder;
    }

    /**
     * Set active
     *
     * @param boolean $active
     *
     * @return ContestSite
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Get teams
     *
     * @return Collection
     */
    public function getTeams()
    {
        return $this->teams;
    }
}
