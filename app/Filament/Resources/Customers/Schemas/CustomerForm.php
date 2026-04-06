<?php

namespace App\Filament\Resources\Customers\Schemas;

use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Section 1: Customer Info ──────────────────────────────
                Section::make('Customer Info')
                    ->description('Basic contact information')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_interested')
                            ->label('Interested in subscribing?')
                            ->default(true)
                            ->inline(false)
                            ->live()
                            ->columnSpan(2),
                            
                        Select::make('reason_category')
                            ->label('Kategori Alasan')
                            ->options([
                                'Tidak Tercover' => 'Tidak Tercover',
                                'Harga Terlalu Mahal' => 'Harga Terlalu Mahal',
                                'Sudah Ada Provider Lain' => 'Sudah Ada Provider Lain',
                                'Tidak Butuh Internet' => 'Tidak Butuh Internet',
                                'Lainnya' => 'Lainnya',
                            ])
                            ->required()
                            ->hidden(fn (Get $get) => $get('is_interested'))
                            ->live(),

                        Textarea::make('reason_description')
                            ->label('Deskripsi Alasan')
                            ->required()
                            ->hidden(fn (Get $get) => $get('is_interested'))
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label('Email (optional)')
                            ->email()
                            ->maxLength(255),
                    ]),

                // ── Section 2: Address ────────────────────────────────────
                Section::make('Address')
                    ->description('Customer location and administrative area')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->schema([
                        Select::make('province')
                            ->label('Province')
                            ->options(Province::pluck('name', 'name'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('city', null);
                                $set('district', null);
                                $set('village', null);
                                $set('zip_code', null);
                            }),

                        Select::make('city')
                            ->label('City / Kabupaten')
                            ->options(function (Get $get) {
                                $province = Province::where('name', $get('province'))->first();
                                if (!$province) return [];
                                return City::where('province_code', $province->code)->pluck('name', 'name');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => blank($get('province')))
                            ->afterStateUpdated(function (Set $set) {
                                $set('district', null);
                                $set('village', null);
                                $set('zip_code', null);
                            }),

                        Select::make('district')
                            ->label('District / Kecamatan')
                            ->options(function (Get $get) {
                                $city = City::where('name', $get('city'))
                                    ->whereHas('province', fn($q) => $q->where('name', $get('province')))
                                    ->first();
                                if (!$city) return [];
                                return District::where('city_code', $city->code)->pluck('name', 'name');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => blank($get('city')))
                            ->afterStateUpdated(function (Set $set) {
                                $set('village', null);
                                $set('zip_code', null);
                            }),

                        Select::make('village')
                            ->label('Village / Kelurahan')
                            ->options(function (Get $get) {
                                $district = District::where('name', $get('district'))
                                    ->whereHas('city', fn($q) => $q->where('name', $get('city')))
                                    ->first();
                                if (!$district) return [];
                                return Village::where('district_code', $district->code)->pluck('name', 'name');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => blank($get('district')))
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if (blank($state)) return;
                                $village = Village::where('district_code', function($q) use($get) {
                                        $q->select('code')->from('indonesia_districts')->where('name', $get('district'))->limit(1);
                                    })
                                    ->where('name', $state)
                                    ->first();
                                if ($village) {
                                    $set('zip_code', $village->postal_code ?? '');
                                }
                            }),

                        Grid::make(3)->schema([
                            TextInput::make('rt')->label('RT')->maxLength(5),
                            TextInput::make('rw')->label('RW')->maxLength(5),
                            TextInput::make('zip_code')->label('ZIP Code')->maxLength(10),
                        ])->columnSpan(2),

                        Textarea::make('full_address')
                            ->label('Full Address')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // ── Section 3: Map Location ───────────────────────────────
                Section::make('Map Location')
                    ->description('Drag the pin or enter coordinates manually')
                    ->icon('heroicon-o-globe-alt')
                    ->collapsed()
                    ->schema([
                        Map::make('location')
                            ->label('')
                            ->columnSpanFull()
                            ->defaultLocation(-6.200000, 106.816666)
                            ->afterStateUpdated(function (?array $state, Set $set) {
                                $set('latitude', $state['lat'] ?? null);
                                $set('longitude', $state['lng'] ?? null);
                            })
                            ->afterStateHydrated(function (?array $state, Set $set, Get $get) {
                                $lat = $get('latitude');
                                $lng = $get('longitude');
                                if ($lat && $lng) {
                                    $set('location', ['lat' => (float)$lat, 'lng' => (float)$lng]);
                                }
                            })
                            ->dehydrated(false),
                        
                        Grid::make(2)->schema([
                            TextInput::make('latitude')
                                ->label('Latitude')
                                ->required()
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        $set('location', ['lat' => (float)$state, 'lng' => (float)($set->get('longitude') ?? 0)]);
                                    }
                                }),
                            TextInput::make('longitude')
                                ->label('Longitude')
                                ->required()
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    if ($state) {
                                        $set('location', ['lat' => (float)($get('latitude') ?? 0), 'lng' => (float)$state]);
                                    }
                                }),
                        ]),
                    ]),

                // ── Section 4: Notes & Evidence ───────────────────────────
                Section::make('Notes & Evidence')
                    ->icon('heroicon-o-camera')
                    ->columns(1)
                    ->schema([
                        Textarea::make('notes')
                            ->label('Additional Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('photo_evidence')
                            ->label('Photo Evidence')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}
