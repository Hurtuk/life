/* "Barrel" of Http Interceptors */
import { HTTP_INTERCEPTORS } from '@angular/common/http';

import { AngularDateHttpInterceptor } from './app/interceptors/AngularDateHttpInterceptor';

/** Http interceptor providers in outside-in order */
export const httpInterceptorProviders = [
  { provide: HTTP_INTERCEPTORS, useClass: AngularDateHttpInterceptor, multi: true },
];