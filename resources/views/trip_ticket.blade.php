<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    @php
        $cpsuLogoPath = public_path('images/cpsu_logo.png');
        $bagongLogoPath = public_path('images/bagong_pilipinas.png');

        $cpsuLogo = file_exists($cpsuLogoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($cpsuLogoPath))
            : '';

        $bagongLogo = file_exists($bagongLogoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($bagongLogoPath))
            : '';
    @endphp

    <style>

        /* =========================================
           PAGE
           ========================================= */

        @page {
            size: 8.5in 13in;
            margin: 8mm 12mm 5mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.2;
        }

        .wrapper {
            padding: 4mm 4mm 0 4mm;
            max-width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .no-border td,
        .no-border th {
            border: none;
        }

        .bold {
            font-weight: bold;
        }


        /* =========================================
           HEADER
           ========================================= */

        .header-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .header-table .logo-col {
            width: 170px;
            position: relative;
            height: 55px;
            vertical-align: middle;
        }

        .header-table .logo-col img {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            height: auto;
        }

        .header-table .logo-col img:first-child {
            width: 40px;
            left: 165px;
        }

        .header-table .logo-col img:nth-child(2) {
            width: 45px;
            left: 205px;
        }

        .header-table .name-col {
            width: auto;
            text-align: center;
            vertical-align: middle;
        }

        .header-table .spacer-col {
            width: 170px;
        }

        .school-name {
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
            display: inline-block;
            text-align: center;
        }

        .school-address {
            font-size: 10px;
            margin-top: 1mm;
        }


        /* =========================================
           TITLE
           ========================================= */

        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin: 5mm 0 6mm;
        }


        /* =========================================
           INSTRUCTIONS
           ========================================= */

        .instruction-table td {
            border: none;
            padding: 0;
            vertical-align: top;
            font-size: 10px;
        }

        .instruction-table .instruction-col {
            width: 62%;
            line-height: 1.5;
        }

        .instruction-table .ticket-col {
            width: 38%;
            padding-left: 10mm;
            padding-top: 1mm;
            font-weight: bold;
        }


        /* =========================================
           DETAILS
           ========================================= */

        .details-table {
            margin-top: 5mm;
            margin-bottom: 6mm;
        }

        .details-table td {
            border: none;
            padding: 0 0 4mm 0;
            font-size: 10px;
        }

        .details-table .left-col {
            width: 55%;
        }

        .details-table .right-col {
            width: 45%;
        }

        .details-table .label {
            font-weight: bold;
        }


        /* =========================================
           PASSENGERS
           ========================================= */

        .section-title {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 1.5mm;
        }

        .passenger-table th,
        .passenger-table td {
            border: 1px solid #000;
            padding: 1.5mm;
            font-size: 9px;
        }

        .passenger-table th {
            text-align: center;
            font-weight: bold;
        }

        .passenger-table .name-col {
            width: 25%;
        }

        .passenger-table .sig-col {
            width: 25%;
        }

        .passenger-note {
            font-size: 9px;
            margin-top: 2mm;
        }


        /* =========================================
           AUTHORIZED
           ========================================= */

        .authorized-table {
            margin-top: 4mm;
        }

        .authorized-table td {
            border: none;
            padding: 0;
            font-size: 9px;
        }

        .authorized-table .spacer-col {
            width: 48%;
        }

        .authorized-table .sig-col {
            width: 52%;
            text-align: left;
            line-height: 1.3;
            padding-top: 8px;
            padding-left: 8mm;
        }

        .authorized-label {
            display: block;
            margin-top: 2px;
            margin-bottom: 2px;
        }

        .authorized-name {
            display: block;
            font-size: 11px;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 2px;
            margin-bottom: 2px;
        }

        .authorized-role {
            display: block;
            text-align: left;
            padding-left: 14mm;
        }


        /* =========================================
           DRIVER
           ========================================= */

        .driver-title {
            font-size: 10px;
            font-weight: bold;
            font-style: italic;
            margin: 3mm 0 1.5mm;
        }


        /* =========================================
           TRIP TABLE
           ========================================= */

        .trip-table th,
        .trip-table td {
            border: 1px solid #000;
            padding: 0;
            text-align: center;
            vertical-align: middle;
            font-size: 8px;
        }

        .trip-table thead tr:first-child th {
            height: 5.5mm;
        }

        .trip-table thead tr:nth-child(2) th {
            height: 8mm;
        }

        .trip-table tbody td {
            height: 5mm;
        }

        .trip-table .trip-no {
            width: 6%;
        }

        .trip-table .date {
            width: 9%;
        }

        .trip-table .time {
            width: 9%;
        }

        .trip-table .place {
            width: 14%;
        }

        .trip-table .odometer {
            width: 10%;
        }

        .trip-table .distance {
            width: 9%;
        }

        .trip-table .budget {
            width: 10%;
        }


        /* =========================================
           FUEL USED
           ========================================= */

        .fuel-title {
            font-size: 10px;
            font-weight: bold;
            margin: 3mm 0 1mm;
        }

        .fuel-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
        }

        .fuel-grid td {
            border: none;
            padding: 0.8mm 0;
            font-size: 9px;
            vertical-align: bottom;
        }

        .fuel-grid .fuel-left {
            width: 50%;
            text-align: left;
        }

        .fuel-grid .fuel-right {
            width: 50%;
            text-align: left;
            padding-left: 25px;
        }

        .fuel-item {
            display: inline-block;
            white-space: nowrap;
            line-height: 1.1;
        }

        .fuel-label {
            display: inline-block;
            white-space: nowrap;
        }

        .fuel-line {
            display: inline-block;
            width: 60px;
            height: 10px;
            border-bottom: 1px solid #000;
            margin-left: 4px;
            vertical-align: bottom;
        }

        .fuel-indent {
            padding-left: 14px;
        }


        /* =========================================
           CERTIFIED CORRECT
           ========================================= */

        /*
         * IMPORTANT:
         * Dili na fixed/absolute.
         * Natural document flow na siya para dili
         * mag-overlap sa A4, Letter, Legal, Long Bond, etc.
         */

        .certified-table {
            width: 40%;
            margin-left: 60%;
            margin-top: 4mm;
            margin-bottom: 2mm;
            table-layout: fixed;
        }

        .certified-table td {
            border: none;
            padding: 0;
            font-size: 9px;
            vertical-align: bottom;
        }

        .certified-table .cert-spacer {
            width: 0;
        }

        .certified-table .cert-label {
            width: 45%;
            white-space: nowrap;
        }

        .certified-table .cert-line {
            width: 55%;
        }

        .certified-table tr:last-child td[colspan="2"] {
            text-align: left;
            padding-left: 0;
            padding-top: 4px;
        }

        .fuel-driver-signature {
            text-align: left;
            font-size: 9px;
            padding-top: 1mm;
            padding-left: 0;
        }


        /* =========================================
           FOOTER
           ========================================= */

        .footer-single {
            width: 100%;
            margin-top: 12mm;
            padding: 0;

            text-align: center;

            font-size: 7px;
            line-height: 1;

            display: block !important;
            visibility: visible !important;
        }


        /* =========================================
           PRINT
           ========================================= */

        @media print {

            html,
            body {
                margin: 0;
                padding: 0;
                background: #fff;
            }

            .wrapper {
                padding: 4mm 4mm 0 4mm;
            }

            /*
             * DO NOT put another @page here.
             * The main @page above controls the paper.
             */

            .certified-table {
                position: static;
                width: 40%;
                margin-left: 60%;
                margin-top: 4mm;
            }

            .footer-single {
                position: static;
                width: 100%;
                margin-top: 12mm;

                display: block !important;
                visibility: visible !important;
            }
        }

    </style>
</head>


<body>

<div class="wrapper">


    <!-- =========================================
         HEADER
         ========================================= -->

    <table class="header-table">

        <tr>

            <td class="logo-col">

                @if($cpsuLogo)

                    <img src="{{ $cpsuLogo }}" alt="CPSU Logo">

                @endif


                @if($bagongLogo)

                    <img src="{{ $bagongLogo }}" alt="Bagong Pilipinas Logo">

                @endif

            </td>


            <td class="name-col">

                <div class="school-name">
                    CENTRAL PHILIPPINES STATE UNIVERSITY
                </div>

                <div class="school-address">
                    Kabankalan City, Negros Occidental
                </div>

            </td>


            <td class="spacer-col"></td>

        </tr>

    </table>


    <!-- =========================================
         TITLE
         ========================================= -->

    <div class="title">
        VEHICLE TRIP TICKET
    </div>


    <!-- =========================================
         INSTRUCTIONS
         ========================================= -->

    <table class="instruction-table no-border">

        <tr>

            <td class="instruction-col">

                <div class="bold">
                    INSTRUCTION:
                </div>

                1. Accomplish in 3 Copies.<br>

                2. Original copy to be return to the Supply Officer
                with duplicate copy of Vale.<br>

                3. Duplicate copy to be furnished to the Official User.<br>

                4. Triplicate copy to be furnished to the Security Guard
                on duty.

            </td>


            <td class="ticket-col">

                Trip Ticket No:

            </td>

        </tr>

    </table>


    <!-- =========================================
         DETAILS
         ========================================= -->

    <table class="details-table no-border">

        <tr>

            <td class="left-col">

                <span class="label">
                    Date:
                </span>

                {{ \Carbon\Carbon::parse($trip->scheduled_departure)->format('m/d/Y') }}

            </td>


            <td class="right-col">

                <span class="label">
                    Vehicle:
                </span>

                {{ $trip->vehicle->name ?? '' }}

            </td>

        </tr>


        <tr>

            <td class="left-col">

                <span class="label">
                    Driver:
                </span>

                {{ $trip->driver->name ?? '' }}

            </td>


            <td class="right-col">

                <span class="label">
                    Contact No:
                </span>

                {{ $trip->driver->contact_number ?? '' }}

            </td>

        </tr>


        <tr>

            <td class="left-col">

                <span class="label">
                    License No:
                </span>

                {{ $trip->driver->license_number ?? '' }}

            </td>


            <td class="right-col">

                <span class="label">
                    Plate No:
                </span>

                {{ $trip->vehicle->plate_no ?? '' }}

            </td>

        </tr>


        <tr>

            <td colspan="2">

                <span class="label">
                    Destination:
                </span>

                {{ $trip->origin }} to {{ $trip->destination }}

            </td>

        </tr>


        <tr>

            <td colspan="2">

                <span class="label">
                    Purpose:
                </span>

                {{ $trip->purpose }}

            </td>

        </tr>

    </table>


    <!-- =========================================
         AUTHORIZED PASSENGERS
         ========================================= -->

    <div class="section-title">
        Authorized Passenger/s:
    </div>


    <table class="passenger-table">

        <tr>

            <th class="name-col">
                Name
            </th>

            <th class="sig-col">
                Signature
            </th>

            <th class="name-col">
                Name
            </th>

            <th class="sig-col">
                Signature
            </th>

        </tr>


        @php

            $passengers = $trip->passengers;

            $left = $passengers
                ->slice(0, 5)
                ->values();

            $right = $passengers
                ->slice(5, 5)
                ->values();

        @endphp


        @for ($i = 0; $i < 5; $i++)

            <tr>

                <td>
                    {{ $i + 1 }}.
                    {{ $left[$i]->name ?? '' }}
                </td>

                <td></td>

                <td>
                    {{ $i + 6 }}.
                    {{ $right[$i]->name ?? '' }}
                </td>

                <td></td>

            </tr>

        @endfor

    </table>


    <div class="passenger-note">

        Authorized Passenger/s hereby certify that the vehicle was used
        on official business as stated above.

    </div>


    <!-- =========================================
         AUTHORIZED BY
         ========================================= -->

    <table class="authorized-table no-border">

        <tr>

            <td class="spacer-col"></td>

            <td class="sig-col">

                <span class="authorized-label">
                    Authorized by:
                </span>

                <span class="authorized-name">
                    ALADINO C. MORACA, Ph.D
                </span>

                <span class="authorized-role">
                    President
                </span>

            </td>

        </tr>

    </table>


    <!-- =========================================
         DRIVER SECTION
         ========================================= -->

    <div class="driver-title">
        To be filled out by the driver
    </div>


    <!-- =========================================
         TRIP TABLE
         ========================================= -->

    @php
        $departure = $trip->scheduled_departure ? \Carbon\Carbon::parse($trip->scheduled_departure) : null;
        $movements = $trip->relationLoaded('movements') ? $trip->movements : collect();
    @endphp

    <table class="trip-table">

        <thead>

            <tr>

                <th
                    class="trip-no"
                    rowspan="2"
                >
                    Trip<br>
                    No.
                </th>


                <th
                    class="date"
                    rowspan="2"
                >
                    Date
                </th>


                <th
                    colspan="3"
                    style="width:33%;"
                >
                    DEPARTURE
                </th>


                <th
                    colspan="3"
                    style="width:33%;"
                >
                    ARRIVAL
                </th>


                <th
                    class="distance"
                    rowspan="2"
                >
                    Distance<br>
                    Travelled<br>
                    (kms)
                </th>


                <th
                    class="budget"
                    rowspan="2"
                >
                    Budget<br>
                    Charging
                </th>

            </tr>


            <tr>

                <th class="time">
                    Time<br>
                    (AM/PM)
                </th>

                <th class="place">
                    Place
                </th>

                <th class="odometer">
                    Odometer<br>
                    Reading
                </th>

                <th class="time">
                    Time<br>
                    (AM/PM)
                </th>

                <th class="place">
                    Place
                </th>

                <th class="odometer">
                    Odometer<br>
                    Reading
                </th>

            </tr>

        </thead>


        <tbody>

            @for ($i = 1; $i <= 8; $i++)

                <tr>

                    @php
                        $movement = $movements->firstWhere('movement_no', $i);
                        $scheduledDeparture = $movement?->scheduled_departure
                            ? \Carbon\Carbon::parse($movement->scheduled_departure)
                            : null;
                        $actualDeparture = $movement?->actual_departure_at
                            ? \Carbon\Carbon::parse($movement->actual_departure_at)
                            : null;
                        $actualArrival = $movement?->actual_arrival_at
                            ? \Carbon\Carbon::parse($movement->actual_arrival_at)
                            : null;
                        $movementDate = $actualDeparture ?? $scheduledDeparture;
                    @endphp

                    <td>
                        {{ $i }}
                    </td>

                    <td>{{ $movementDate?->format('m/d/Y') }}</td>
                    <td>{{ $actualDeparture?->format('g:i A') ?? $scheduledDeparture?->format('g:i A') }}</td>
                    <td>{{ $movement?->origin }}</td>
                    <td></td>
                    <td>{{ $actualArrival?->format('g:i A') }}</td>
                    <td>{{ $movement?->destination }}</td>
                    <td></td>
                    <td></td>
                    <td></td>

                </tr>

            @endfor


            <tr>

                <td
                    colspan="10"
                    style="
                        text-align:left;
                        font-weight:bold;
                        border-width:2px;
                    "
                >
                    Total Distance Travelled
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                </td>

            </tr>

        </tbody>

    </table>


    <!-- =========================================
         FUEL USED
         ========================================= -->

    <div class="fuel-title">
        Fuel Used
    </div>


    <table class="fuel-grid no-border">

        <tr>

            <td class="fuel-left">

                <div class="fuel-item">

                    <span class="fuel-label">
                        Balance in Tank
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>


            <td class="fuel-right">

                <div class="fuel-item">

                    <span class="fuel-label">
                        Gear Oil Used
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>

        </tr>


        <tr>

            <td class="fuel-left">

                <div class="fuel-item fuel-indent">

                    <span class="fuel-label">
                        Add: Issued from Stock
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>


            <td class="fuel-right">

                <div class="fuel-item">

                    <span class="fuel-label">
                        Lubricant Oil Used
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>

        </tr>


        <tr>

            <td class="fuel-left">

                <div class="fuel-item fuel-indent">

                    <span class="fuel-label">
                        Add: Purchased Outside
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>


            <td class="fuel-right">

                <div class="fuel-item">

                    <span class="fuel-label">
                        Grease Used
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>

        </tr>


        <tr>

            <td class="fuel-left">

                <div class="fuel-item fuel-indent">

                    <span class="fuel-label">
                        Less: Fuel Used
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>


            <td class="fuel-right"></td>

        </tr>


        <tr>

            <td class="fuel-left">

                <div class="fuel-item">

                    <span class="fuel-label">
                        Balance in Tank (End of Trip)
                    </span>

                    <span class="fuel-line"></span>

                </div>

            </td>


            <td class="fuel-right"></td>

        </tr>

    </table>


    <!-- =========================================
         CERTIFIED CORRECT
         ========================================= -->

    <table class="certified-table">

        <tr>

            <td class="cert-spacer"></td>

            <td class="cert-label">
                Certified Correct:
            </td>

            <td class="cert-line"></td>

        </tr>


        <tr>

            <td class="cert-spacer"></td>

            <td
                colspan="2"
                class="fuel-driver-signature"
            >
                Name &amp; Signature of Driver
            </td>

        </tr>

    </table>


    <!-- =========================================
         FOOTER
         ========================================= -->

    <div class="footer-single">

        Doc Control Code: CPSU-F-PPMGSO-14,

        &nbsp;&nbsp;&nbsp;&nbsp;

        Effective Date: 06/19/2025,

        &nbsp;&nbsp;&nbsp;&nbsp;

        Page No.: 1 of 1

    </div>


</div>

</body>
</html>