<?php

namespace Sqids;

class Sqids
{
    public function __construct(
        private string $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
        private int $minLength = 0,
        private array $blocklist = [],
    ) {

        // @todo check minimum length of the alphabet
        // @todo check that `minLength` >= 0 && `minLength` <= `alphabet.length`
        // @todo exclude words from `blocklist` that contain characters not in the alphabet

        $this->alphabet = $this->shuffle($this->alphabet);
    }

    /**
     * Encodes an array of unsigned integers into an ID
     *
     * @param array $numbers Positive integers to encode into an ID
     * @return string Generated ID
     */
    public function encode(array $numbers): string
    {
        // @todo check that no negative numbers
        // @todo check that numbers are not greater than `this.maxValue()`

        return $this->encodeNumbers($numbers, false);
    }

    /**
     * Internal function that encodes an array of unsigned integers into an ID
     *
     * @param array $numbers Positive integers to encode into an ID
     * @param bool $partitioned If true, the first number is always a throwaway number (used either for blocklist or padding)
     * @return string Generated ID
     */
    private function encodeNumbers(array $numbers, bool $partitioned = false): string
    {
        // get a semi-random offset from input numbers
        $offset = count($numbers);
        foreach ($numbers as $i => $v) {
            $offset = ($v % strlen($this->alphabet)) * ($i + 1) + $offset;
        }
        $offset = $offset % strlen($this->alphabet);

        // re-arrange alphabet so that second-half goes in front of the first-half
        $alphabet = substr($this->alphabet, $offset) . substr($this->alphabet, 0, $offset);

        // prefix is the first character in the generated ID, used for randomization
        $prefix = $alphabet[0];

        // partition is the character used instead of the first separator to indicate that the first number in the input array is a throwaway number. this character is used only once to handle blocklist and/or padding
        $partition = $alphabet[1];

        // alphabet should not contain `prefix` or `partition` reserved characters
        $alphabet = substr($alphabet, 2);

        // final ID will always have the `prefix` character at the beginning
        $ret = [$prefix];

        // encode input array
        for ($i = 0; $i != count($numbers); $i++) {
            $num = $numbers[$i];

            // the last character of the alphabet is going to be reserved for the `separator`
            $alphabetWithoutSeparator = substr($alphabet, 0, -1);
            $ret[] = $this->toId($num, $alphabetWithoutSeparator);

            // execute only if this is not the last number
            if ($i < count($numbers) - 1) {
                // `separator` character is used to isolate numbers within the ID
                $separator = substr($alphabet, -1);

                // for the barrier use the `separator` unless this is the first iteration and the first number is a throwaway number - then use the `partition` character
                if ($partitioned && $i == 0) {
                    $ret[] = $partition;
                } else {
                    $ret[] = $separator;
                }

                // shuffle on every iteration
                $alphabet = $this->shuffle($alphabet);
            }
        }

        // join all the parts to form an ID
        $id = implode('', $ret);

        // if `minLength` is used and the ID is too short, add a throwaway number & start over
        if ($this->minLength > strlen($id)) {
            $partitionNumber = $this->toNumber(
                substr($alphabet, 0, $this->minLength - strlen($id)),
                $alphabet
            );

            if ($partitioned) {
                $numbers[0] = $partitionNumber;
            } else {
                array_unshift($numbers, $partitionNumber);
            }

            $id = $this->encodeNumbers($numbers, true);
        }

        // if ID has a blocked word anywhere, add a throwaway number & start over
        if ($this->isBlockedId($id)) {
            if ($partitioned) {
                $numbers[0] += 1;
            } else {
                array_unshift($numbers, 0);
            }

            $id = $this->encodeNumbers($numbers, true);
        }

        return $id;
    }

    /**
     * Decodes an ID back into an array of unsigned integers
     *
     * @param string $id Encoded ID
     * @return array Array of unsigned integers
     */
    public function decode(string $id): array
    {
        // @todo check that characters are in the alphabet

        $ret = [];
        $originalId = $id;

        // first character is always the `prefix`
        $prefix = $id[0];

        // `offset` is the semi-random position that was generated during encoding
        $offset = strpos($this->alphabet, $prefix);

        // re-arrange alphabet back into it's original form
        $alphabet = substr($this->alphabet, $offset) . substr($this->alphabet, 0, $offset);

        // `partition` character is in second position
        $partition = $alphabet[1];

        // alphabet has to be without reserved `prefix` & `partition` characters
        $alphabet = substr($alphabet, 2);

        // now it's safe to remove the prefix character from ID, it's not needed anymore
        $id = substr($id, 1);

        // decode
        while (strlen($id)) {
            // the first separator might be either `separator` or `partition` character. if partition character is anywhere in the generated ID, then the ID has throwaway number
            $separator = substr($alphabet, -1);
            if (strpos($id, $partition) !== false) {
                $separator = $partition;
            }

            // we need the first part to the left of the separator to decode the number
            $chunks = explode($separator, $id, 2);
            if (count($chunks)) {
                // decode the number without using the `separator` character
                $alphabetWithoutSeparator = substr($alphabet, 0, -1);
                $ret[] = $this->toNumber($chunks[0], $alphabetWithoutSeparator);

                // if this ID has multiple numbers, shuffle the alphabet because that's what encoding function did
                if (count($chunks) > 1) {
                    $alphabet = $this->shuffle($alphabet);
                }
            }

            // `id` is now going to be everything to the right of the `separator`
            $id = count($chunks) > 1 ? $chunks[1] : '';
        }

        // if original ID contains a `partition` character, remove the first number (it's junk)
        if (strpos($originalId, $partition) !== false) {
            array_shift($ret);
        }

        // if re-encoding does not produce the same result, ID is invalid
        if ($this->encode($ret) != $originalId) {
            $ret = [];
        }

        return $ret;
    }

    // always zero for every language
    public function minValue()
    {
        return 0;
    }

    // depends on the programming language & implementation
    public function maxValue()
    {
        return PHP_INT_MAX;
    }

    // consistent shuffle (always produces the same result given the input)
    private function shuffle(string $alphabet): string
    {
        $chars = str_split($alphabet);

        for ($i = 0, $j = count($chars) - 1; $j > 0; $i++, $j--) {
            $r = ($i + ord($chars[$i]) + ord($chars[$j]) + $j) % count($chars);
            [$chars[$j], $chars[$r]] = [$chars[$r], $chars[$j]];
        }

        return implode('', $chars);
    }

    private function toId(int $num, string $alphabet): string
    {
        $id = [];
        $chars = str_split($alphabet);

        $result = $num;

        do {
            array_unshift($id, $chars[$result % count($chars)]);
            $result = (int) floor($result / count($chars));
        } while ($result > 0);

        return implode('', $id);
    }

    private function toNumber(string $id, string $alphabet): int
    {
        $chars = str_split($alphabet);
        return array_reduce(str_split($id), function ($a, $v) use ($chars) {
            return $a * count($chars) + array_search($v, $chars);
        }, 0);
    }

    private function isBlockedId(string $id): bool
    {
        foreach ($this->blocklist as $word) {
            if (stripos($id, $word) !== false) {
                return true;
            }
        }

        return false;
    }
}
