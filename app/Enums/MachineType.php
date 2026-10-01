<?php

namespace App\Enums;

enum MachineType: string
{
    case Filling = 'filling';
    case Labeling = 'labeling';
    case InjectBlow = 'inject_blow';
    case Tom = 'tom';
    case Mixer = 'mixer';
    case Timbangan = 'timbangan';
    case Coding = 'coding';
    case Vehicle = 'vehicle';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::Filling => 'Filling',
            self::Labeling => 'Labeling',
            self::InjectBlow => 'Inject Blow',
            self::Tom => 'TOM',
            self::Mixer => 'Mixer',
            self::Timbangan => 'Timbangan',
            self::Coding => 'Coding',
            self::Vehicle => 'Kendaraan',
            self::Generic => 'Umum',
        };
    }
}
