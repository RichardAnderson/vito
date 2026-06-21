<?php

namespace Vito\Plugin\Contracts;

/**
 * Capability facade key for server/host file storage.
 *
 * RESERVED, not bound in the foundation slice. Resolving it throws until the
 * capability chokepoint (manifest "storage" + server-scoped path policy) lands,
 * so plugin authors must not treat it as callable yet.
 */
interface Storage {}
