<?php

namespace Vito\Plugin\Contracts;

/**
 * Capability facade key for outbound HTTP requests.
 *
 * RESERVED, not bound in the foundation slice. Resolving it throws until the
 * capability chokepoint (manifest "outbound-http") lands, so plugin authors must
 * not treat it as callable yet.
 */
interface Http {}
