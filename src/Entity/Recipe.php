<?php

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $ingrédients = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $steps = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeMinutes = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $difficulty = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tags = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, RecipePhoto>
     */
    #[ORM\OneToMany(targetEntity: RecipePhoto::class, mappedBy: 'recipe', orphanRemoval: true)]
    private Collection $recipePhotos;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->recipePhotos = new ArrayCollection();
    }   

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getIngrédients(): ?string
    {
        return $this->ingrédients;
    }

    public function setIngrédients(string $ingrédients): static
    {
        $this->ingrédients = $ingrédients;

        return $this;
    }

    public function getSteps(): ?string
    {
        return $this->steps;
    }

    public function setSteps(string $steps): static
    {
        $this->steps = $steps;

        return $this;
    }

    public function getTimeMinutes(): ?int
    {
        return $this->timeMinutes;
    }

    public function setTimeMinutes(?int $timeMinutes): static
    {
        $this->timeMinutes = $timeMinutes;

        return $this;
    }

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function setDifficulty(?string $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getTags(): ?string
    {
        return $this->tags;
    }

    public function setTags(?string $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, RecipePhoto>
     */
    public function getRecipePhotos(): Collection
    {
        return $this->recipePhotos;
    }

    public function addRecipePhoto(RecipePhoto $recipePhoto): static
    {
        if (!$this->recipePhotos->contains($recipePhoto)) {
            $this->recipePhotos->add($recipePhoto);
            $recipePhoto->setRecipe($this);
        }

        return $this;
    }

    public function removeRecipePhoto(RecipePhoto $recipePhoto): static
    {
        if ($this->recipePhotos->removeElement($recipePhoto)) {
            // set the owning side to null (unless already changed)
            if ($recipePhoto->getRecipe() === $this) {
                $recipePhoto->setRecipe(null);
            }
        }

        return $this;
    }
}
