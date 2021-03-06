<?php
declare(strict_types=1);

namespace DrdPlus\Person;

use DrdPlus\Armourer\Armourer;
use DrdPlus\Codes\GenderCode;
use DrdPlus\CurrentProperties\CurrentProperties;
use DrdPlus\Equipment\Equipment;
use DrdPlus\Health\Health;
use DrdPlus\Person\Attributes\Name;
use DrdPlus\Background\Background;
use DrdPlus\GamingSession\Memories;
use DrdPlus\Person\ProfessionLevels\ProfessionLevels;
use DrdPlus\Professions\Profession;
use DrdPlus\PropertiesByFate\PropertiesByFate;
use DrdPlus\PropertiesByLevels\PropertiesByLevels;
use DrdPlus\Properties\Body\Age;
use DrdPlus\Properties\Body\HeightInCm;
use DrdPlus\Properties\Body\BodyWeightInKg;
use DrdPlus\Races\Race;
use DrdPlus\Skills\Skills;
use DrdPlus\Stamina\Stamina;
use DrdPlus\Tables\Measurements\Experiences\ExperiencesTable;
use DrdPlus\Tables\Measurements\Experiences\Level as LevelBonus;
use DrdPlus\Tables\Tables;
use Granam\Strict\Object\StrictObject;

class Person extends StrictObject
{
    /**
     * @var Name
     */
    private $name;
    /**
     * @var Race
     */
    private $race;
    /**
     * @var GenderCode
     */
    private $genderCode;
    /**
     * @var PropertiesByFate
     */
    private $propertiesByFate;
    /**
     * @var PropertiesByLevels
     * Does not need Doctrine annotation - it is just an on-demand built container
     */
    private $propertiesByLevels;
    /**
     * @var ProfessionLevels
     */
    private $professionLevels;
    /**
     * @var Memories
     */
    private $memories;
    /**
     * @var Health
     */
    private $health;
    /**
     * @var Stamina
     */
    private $stamina;
    /**
     * @var Background
     */
    private $background;
    /**
     * @var Skills
     */
    private $skills;
    /**
     * @var BodyWeightInKg
     */
    private $bodyWeightInKgAdjustment;
    /**
     * @var HeightInCm
     */
    private $heightInCm;
    /**
     * @var Age
     */
    private $age;
    /**
     * @var Equipment
     */
    private $equipment;

    /**
     * @param Race $race
     * @param GenderCode $genderCode
     * @param Name $name
     * @param PropertiesByFate $propertiesByFate
     * @param Memories $memories
     * @param ProfessionLevels $professionLevels
     * @param Background $background
     * @param Skills $skills
     * @param BodyWeightInKg $weightInKgAdjustment
     * @param HeightInCm $heightInCm
     * @param Age $age
     * @param Equipment $equipment
     * @param Tables $tables
     * @throws \DrdPlus\Person\Exceptions\InsufficientExperiences
     */
    public function __construct(
        Name $name,
        Race $race,
        GenderCode $genderCode,
        PropertiesByFate $propertiesByFate,
        Memories $memories,
        ProfessionLevels $professionLevels,
        Background $background,
        Skills $skills,
        BodyWeightInKg $weightInKgAdjustment,
        HeightInCm $heightInCm,
        Age $age,
        Equipment $equipment,
        Tables $tables
    )
    {
        $this->name = $name;
        $this->race = $race;
        $this->genderCode = $genderCode;
        $this->propertiesByFate = $propertiesByFate;
        $this->checkLevelsAgainstExperiences(
            $professionLevels,
            $memories,
            $tables->getExperiencesTable()
        );
        $this->memories = $memories;
        $this->professionLevels = $professionLevels;
        $this->background = $background;
        $this->skills = $skills;
        $this->bodyWeightInKgAdjustment = $weightInKgAdjustment;
        $this->heightInCm = $heightInCm;
        $this->age = $age;
        $this->equipment = $equipment;
        $this->health = new Health();
        $this->stamina = new Stamina();
    }

    /**
     * @param ProfessionLevels $professionLevels
     * @param Memories $memories
     * @param ExperiencesTable $experiencesTable
     * @throws \DrdPlus\Person\Exceptions\InsufficientExperiences
     */
    private function checkLevelsAgainstExperiences(
        ProfessionLevels $professionLevels,
        Memories $memories,
        ExperiencesTable $experiencesTable
    )
    {
        $highestLevelRank = $professionLevels->getCurrentLevel()->getLevelRank();
        $requiredExperiences = $experiencesTable->toTotalExperiences(
            new LevelBonus($highestLevelRank->getValue(), $experiencesTable)
        );
        $availableExperiences = $memories->getExperiences($experiencesTable);
        if ($availableExperiences->getValue() < $requiredExperiences->getValue()) {
            throw new Exceptions\InsufficientExperiences(
                "Given level {$highestLevelRank} needs at least {$requiredExperiences} experiences, got only {$availableExperiences}"
            );
        }
    }

    /**
     * Name is an enum, therefore a constant in fact, therefore only way how to change the name is to replace it
     *
     * @param Name $name
     * @return $this
     */
    public function setName(Name $name): Person
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): Name
    {
        return $this->name;
    }

    public function getRace(): Race
    {
        return $this->race;
    }

    public function getGenderCode(): GenderCode
    {
        return $this->genderCode;
    }

    public function getPropertiesByFate(): PropertiesByFate
    {
        return $this->propertiesByFate;
    }

    public function getMemories(): Memories
    {
        return $this->memories;
    }

    public function getHealth(): Health
    {
        return $this->health;
    }

    public function getStamina(): Stamina
    {
        return $this->stamina;
    }

    public function getProfessionLevels(): ProfessionLevels
    {
        return $this->professionLevels;
    }

    public function getBackground(): Background
    {
        return $this->background;
    }

    public function getSkills(): Skills
    {
        return $this->skills;
    }

    /**
     * Those are lazy loaded (and re-calculated on every entity reload at first time requested)
     *
     * @param Tables $tables
     * @return PropertiesByLevels
     */
    public function getPropertiesByLevels(Tables $tables): PropertiesByLevels
    {
        if ($this->propertiesByLevels === null) {
            $this->propertiesByLevels = new PropertiesByLevels( // enums aggregate
                $this->getRace(),
                $this->getGenderCode(),
                $this->getPropertiesByFate(),
                $this->getProfessionLevels(),
                $this->bodyWeightInKgAdjustment,
                $this->heightInCm,
                $this->age,
                $tables
            );
        }

        return $this->propertiesByLevels;
    }

    /**
     * @param Tables $tables
     * @param Armourer $armourer
     * @return CurrentProperties
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    public function getCurrentProperties(Tables $tables, Armourer $armourer): CurrentProperties
    {
        return new CurrentProperties(
            $this->getPropertiesByLevels($tables),
            $this->getHealth(),
            $this->getRace(),
            $this->getEquipment()->getWornBodyArmor(),
            $this->getEquipment()->getWornHelm(),
            $this->getEquipment()->getWeight($tables->getWeightTable()),
            $tables,
            $armourer
        );
    }

    public function getProfession(): Profession
    {
        return $this->getProfessionLevels()->getFirstLevel()->getProfession();
    }

    public function getEquipment(): Equipment
    {
        return $this->equipment;
    }

}