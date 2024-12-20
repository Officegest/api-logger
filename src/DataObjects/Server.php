<?php

declare(strict_types=1);

namespace OfficegestApiLogger\DataObjects;

final class Server
{
    /**
     * @param null|string $ip The IP address of the server.
     * @param null|string $timezone The timezone of the server: UTC, America/New_York, Europe/Lisbon,
     * @param null|string $software THe software used on the server.
     * @param null|string $signature The signature of the server software, if there is one.
     * @param null|string $protocol The HTTP protocol used
     * @param null|OS $os The OS object
     * @param null|string $encoding
     * @param null|string $hostname
     */
    public function __construct(
        public null|string $ip,
        public null|string $timezone,
        public null|string $software,
        public null|string $signature,
        public null|string $protocol,
        public null|OS     $os,
        public null|string $encoding,
        public null|string $hostname,
    )
    {
    }

    /**
     * @return array{
     *     ip: null|string,
     *     timezone: null|string,
     *     software: null|string,
     *     signature: null|string,
     *     protocol: null|string,
     *     os: array{
     *         name: null|string,
     *         release: null|string,
     *         architecture: null|string
     *     }|null,
     *     encoding: null|string,
     *     hostname: null|string
     * }
     */
    public function __toArray(): array
    {
        return [
            'ip' => $this->ip,
            'timezone' => $this->timezone,
            'software' => $this->software,
            'signature' => $this->signature,
            'protocol' => $this->protocol,
            'os' => $this->os?->__toArray(),
            'encoding' => $this->encoding,
            'hostname' => $this->hostname,
        ];
    }
}
