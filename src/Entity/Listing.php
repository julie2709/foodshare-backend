<?php

namespace App\Entity;

use App\Repository\ListingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListingRepository::class)]
class Listing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $category = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiryDate = null;

    #[ORM\Column(length: 20)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pickupInfo = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'listings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, ListingPhoto>
     */
    #[ORM\OneToMany( mappedBy: 'listing',
    targetEntity: ListingPhoto::class,
    cascade: ['persist', 'remove'],
    orphanRemoval: true)]
    private Collection $listingPhotos;

    /**
     * @var Collection<int, DonationRequest>
     */
    #[ORM\OneToMany(targetEntity: DonationRequest::class, mappedBy: 'listing', orphanRemoval: true)]
    private Collection $donationRequests;


     public function __construct()
    {
      
      $this->createdAt = new \DateTimeImmutable();
      $this->status = 'DISPONIBLE';
      $this->listingPhotos = new ArrayCollection();
      $this->donationRequests = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(?string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getExpiryDate():  ?\DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function setExpiryDate( ?\DateTimeImmutable $expiryDate): static
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPickupInfo(): ?string
    {
        return $this->pickupInfo;
    }

    public function setPickupInfo(?string $pickupInfo): static
    {
        $this->pickupInfo = $pickupInfo;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, ListingPhoto>
     */
    public function getListingPhotos(): Collection
    {
        return $this->listingPhotos;
    }

    public function addListingPhoto(ListingPhoto $listingPhoto): static
    {
        if (!$this->listingPhotos->contains($listingPhoto)) {
            $this->listingPhotos->add($listingPhoto);
            $listingPhoto->setListing($this);
        }

        return $this;
    }

    public function removeListingPhoto(ListingPhoto $listingPhoto): static
    {
        if ($this->listingPhotos->removeElement($listingPhoto)) {
            // set the owning side to null (unless already changed)
            if ($listingPhoto->getListing() === $this) {
                $listingPhoto->setListing(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DonationRequest>
     */
    public function getDonationRequests(): Collection
    {
        return $this->donationRequests;
    }

    public function addDonationRequest(DonationRequest $donationRequest): static
    {
        if (!$this->donationRequests->contains($donationRequest)) {
            $this->donationRequests->add($donationRequest);
            $donationRequest->setListing($this);
        }

        return $this;
    }

    public function removeDonationRequest(DonationRequest $donationRequest): static
    {
        if ($this->donationRequests->removeElement($donationRequest)) {
            // set the owning side to null (unless already changed)
            if ($donationRequest->getListing() === $this) {
                $donationRequest->setListing(null);
            }
        }

        return $this;
    }
}
