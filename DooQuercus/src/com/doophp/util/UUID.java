package com.doophp.util;

import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.StringValue;
import com.caucho.quercus.env.Value;

/**
 * UUID for PHP quercus
 */
public class UUID {

  public static Value randomUUID(Env env)
  {
    String id = java.util.UUID.randomUUID().toString();
    Value val = StringValue.create(id);
    return val;
  }
}
