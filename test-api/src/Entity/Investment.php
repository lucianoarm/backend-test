<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTimeInterface;

#[ORM\Entity]
class Investment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Owner::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Owner $owner;

    #[ORM\Column(type: 'date')]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: 'float')]
    private float $initialValue;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $redeemedValue = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $redeemedAt = null;

    public function getId(): ?int {
        return $this->id; 
    }

    public function getOwner(): Owner {
        return $this->owner; 
    }

    public function setOwner(Owner $owner): self {
        $this->owner = $owner; 
        return $this; 
    }

    public function getCreatedAt(): DateTimeInterface {
        return $this->createdAt; 
    }
    
    public function setCreatedAt(DateTimeInterface $createdAt): self {
        $this->createdAt = $createdAt;
        return $this; 
    }

    public function getInitialValue(): float {
        return $this->initialValue; 
    }

    public function setInitialValue(float $initialValue): self {
        $this->initialValue = $initialValue;
        return $this;
    }

    public function getRedeemedValue(): ?float {
        return $this->redeemedValue;
    }

    public function setRedeemedValue(?float $redeemedValue): self {
        $this->redeemedValue = $redeemedValue; 
        return $this;
    }
    
    public function getRedeemedAt(): ?DateTimeInterface {
        return $this->redeemedAt;
    }
    
    public function setRedeemedAt(?DateTimeInterface $redeemedAt): self {
        $this->redeemedAt = $redeemedAt;
        return $this;
    }

}
