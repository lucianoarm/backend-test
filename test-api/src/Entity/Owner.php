<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Owner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 250)]
    private string $name;

    #[ORM\Column(length: 150)]
    private string $email;

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }
    
    public function setEmail(string $email): self {
        $this->email = $email;
        return $this;
    }

    public function getEmail(): string {
        return $this->email;
    }
}
